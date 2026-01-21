<?php
namespace Auth\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Interop\Container\ContainerInterface;
use Laminas\Authentication\AuthenticationService;
use Laminas\Db\Adapter\Adapter as DbAdapter;
use Laminas\Authentication\Adapter\DbTable\CredentialTreatmentAdapter as AuthAdapter;
use Laminas\Session\Container;
use Laminas\Authentication\Result;
use Laminas\Mvc\MvcEvent;
use Auth\Form\AuthForm;
use Laminas\Http\Client as HttpClient;
use Laminas\Http\Request as HttpRequest;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Label\Alignment\LabelAlignmentCenter;
use Endroid\QrCode\Writer\PngWriter;//
use Auth\Service\NatsService;
use Administration\Model as Administration;
use Academic\Model as Academic;
use Hr\Model as Hr;
use Laminas\View\Model\JsonModel;
use Laminas\Http\Response;
class AuthController extends AbstractActionController
{
	private $container;
    private $dbAdapter;
    private $natsService;
    protected $_password;// password plugin
    protected $webhookPayload;
    protected $proofRequestPayload;
    private $studentId;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->dbAdapter = $this->container->get(DbAdapter::class);
		 $this->webhookPayload = [
            'webhookId' => 'ATHANG-DEMIS-STAGING_NDI',
            'webhookURL' => 'http://129.154.231.158/auth/webhook',
            'authentication' => [
                'type' => 'OAuth2',
                'version' => 'v1',
                'data' => [
                    'url' => 'https://staging.bhutanndi.com/authentication/v1/authenticate',
                    'grant_type' => 'client_credentials',
                    'client_id' => '3tq7ho23g5risndd90a76jre5f',
                    'client_secret' => '111rvn964mucumr6c3qq3n2poilvq5v92bkjh58p121nmoverquh',
                ]
            ]
        ];

        $this->proofRequestPayload = [
            'proofName' => 'Verify Foundational ID',
            'proofAttributes' => [
                [
                    'name' => 'monk_id',
                    'restrictions' => [
                        [
                            'schema_name' => 'https://dev-schema.ngotag.com/schemas/3f3935de-60f1-4a60-b576-286e439388f6',
                        ]
                    ]
                ]
            ]
        ];
    }

    public function getDefinedTable($table)
    {
        $definedTable = $this->container->get($table);
        return $definedTable;
    }
    public function indexAction()
    {
        $auth = new AuthenticationService();
		if($auth->hasIdentity()):
			return $this->redirect()->toRoute('home');
		else:
			return $this->redirect()->toRoute('auth', array('action' =>'login'));
		endif;
		
        return new ViewModel([
        	'title' => 'Login'
        ]);
    }
    /** 
     * Authentication - Login 
     */
    public function loginAction()
    {
       // $status = $this->params()->fromQuery('status', null);
        //echo '<pre>';print_r($status);exit;
		$messages = null;
		$auth = new AuthenticationService();
        if($auth->hasIdentity() && $this->params()->fromRoute('id') != "NoKeepAlive"):
			 return $this->redirect()->toRoute('home');
        endif;
        if ($this->getRequest()->isPost()) 
		{
			$data = $this->getRequest()->getPost();
            $staticSalt = $this->password()->getStaticSalt();// Get Static Salt using Password Plugin
            if(filter_var($data['username'], FILTER_VALIDATE_EMAIL)):
                $identitycolumn = "email";
            else:
                $identitycolumn = "Student ID";
            endif;
            $authAdapter = new AuthAdapter($this->dbAdapter,
                                           'sys_users', // there is a method setTableName to do the same
                                           $identitycolumn, // there is a method setIdentityColumn to do the same
                                           'password', // there is a method setCredentialColumn to do the same
                                           "SHA1(CONCAT('$staticSalt', ?, salt))" // setCredentialTreatment(parametrized string) 'MD5(?)'
                                          );            
            $authAdapter->setIdentity($data['username'])
                        ->setCredential($data['password']);
            $authService = new AuthenticationService();
            $result = $authService->authenticate($authAdapter);
            //echo"<pre>"; print_r($result); exit;
            switch ($result->getCode()) 
			{
                case Result::FAILURE_IDENTITY_NOT_FOUND:
                    // nonexistent identity
                    $this->flashMessenger()->addMessage("error^ A record with the supplied identity (username) could not be found.");
                    break;

                case Result::FAILURE_CREDENTIAL_INVALID:
                    // invalid credential
                    $this->flashMessenger()->addMessage("info^ Please check Caps Lock key is activated on your computer.");
                    $this->flashMessenger()->addMessage("error^ Supplied credential (password) is invalid, Please try again.");
                    break;

                case Result::SUCCESS:
                    $storage = $authService->getStorage();
                    $storage->write($authAdapter->getResultRowObject());
                    $role = $this->identity()->role;
                    $time = 1209600; // 14 days 1209600/3600 = 336 hours => 336/24 = 14 days
                    if ($data['rememberme']) {
                        $sessionManager = new \Laminas\Session\SessionManager();
                        $sessionManager->rememberMe($time);
                    }
                    $id = $this->identity()->id; 
                    $login = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($id, $column='logins');
                    
                    $data = array(
                            'id'         => $id,
                            'last_login' => date('Y-m-d H:i:s'),
                            'last_accessed_ip' => !empty($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : ( !empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'] ),
                            'logins' => $login + 1
                    ); 
                    $this->getDefinedTable(Administration\UsersTable::class)->save($data);
					//check whether user is block
					$status = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($id, $column='status');
					if($status == "9"){
					   return $this->redirect()->toRoute('auth', array('action' => 'logout', 'id'=>'1'));	
					}
                    $this->flashMessenger()->addMessage("info^ Welcome,</br>You have successfully logged in!");
                    return $this->redirect()->toRoute('home');
                break;

                default:
                    //other failure--- currently silent
                break;  
            }
            return $this->redirect()->toRoute('auth', array('action' => 'login'));
            
			if ( $this->params()->fromRoute('id') == "NoKeepAlive" ):
				$auth = new AuthenticationService();
				$auth->clearIdentity();
				$sessionManager = new \Laminas\Session\SessionManager();
				$sessionManager->forgetMe();
				$this->flashMessenger()->addMessage('warning^Your session has expired, please login again.');
			endif;
        }
        $ViewModel = new ViewModel(array(
			'title' => 'Log into System',
		));
		$ViewModel->setTerminal(false);
		return $ViewModel;
    }
	 /** 
     * Authentication - Login 
     */
    public function loginsuccessAction()
    {
       // $status = $this->params()->fromQuery('status', null);
        //echo '<pre>';print_r($status);exit;
		//$messages = null;
		$auth = new AuthenticationService();
        if($auth->hasIdentity() && $this->params()->fromRoute('id') != "NoKeepAlive"):
			 return $this->redirect()->toRoute('home');
        endif;
        //if ($this->getRequest()->isPost()) 
		//{
			$username = '1234';
            $password ='1234qwer';
            $staticSalt = $this->password()->getStaticSalt();// Get Static Salt using Password Plugin
            if(filter_var($data['username'], FILTER_VALIDATE_EMAIL)):
                $identitycolumn = "email";
            else:
                $identitycolumn = "monk_id";
            endif;
            $authAdapter = new AuthAdapter($this->dbAdapter,
                                           'sys_users', // there is a method setTableName to do the same
                                           $identitycolumn, // there is a method setIdentityColumn to do the same
                                           'password', // there is a method setCredentialColumn to do the same
                                           "SHA1(CONCAT('$staticSalt', ?, salt))" // setCredentialTreatment(parametrized string) 'MD5(?)'
                                          );            
            $authAdapter->setIdentity($username)
                        ->setCredential($password);
            $authService = new AuthenticationService();
            $result = $authService->authenticate($authAdapter);
            //echo"<pre>"; print_r($result); exit;
            switch ($result->getCode()) 
			{
                case Result::FAILURE_IDENTITY_NOT_FOUND:
                    // nonexistent identity
                    $this->flashMessenger()->addMessage("error^ A record with the supplied identity (username) could not be found.");
                    break;

                case Result::FAILURE_CREDENTIAL_INVALID:
                    // invalid credential
                    $this->flashMessenger()->addMessage("info^ Please check Caps Lock key is activated on your computer.");
                    $this->flashMessenger()->addMessage("error^ Supplied credential (password) is invalid, Please try again.");
                    break;

                case Result::SUCCESS:
                    $storage = $authService->getStorage();
                    $storage->write($authAdapter->getResultRowObject());
                    $role = $this->identity()->role;
                    $time = 1209600; // 14 days 1209600/3600 = 336 hours => 336/24 = 14 days
                    if ($data['rememberme']) {
                        $sessionManager = new \Laminas\Session\SessionManager();
                        $sessionManager->rememberMe($time);
                    }
                    $id = $this->identity()->id; 
                    $login = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($id, $column='logins');
                    
                    $data = array(
                            'id'         => $id,
                            'last_login' => date('Y-m-d H:i:s'),
                            'last_accessed_ip' => !empty($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : ( !empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'] ),
                            'logins' => $login + 1
                    ); 
                    $this->getDefinedTable(Administration\UsersTable::class)->save($data);
					//check whether user is block
					$status = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($id, $column='status');
					if($status == "9"){
					   return $this->redirect()->toRoute('auth', array('action' => 'logout', 'id'=>'1'));	
					}
                    $this->flashMessenger()->addMessage("info^ Welcome,</br>You have successfully logged in!");
                    return $this->redirect()->toRoute('home');
                break;

                default:
                    //other failure--- currently silent
                break;  
            }
            return $this->redirect()->toRoute('auth', array('action' => 'login'));
            
			if ( $this->params()->fromRoute('id') == "NoKeepAlive" ):
				$auth = new AuthenticationService();
				$auth->clearIdentity();
				$sessionManager = new \Laminas\Session\SessionManager();
				$sessionManager->forgetMe();
				$this->flashMessenger()->addMessage('warning^Your session has expired, please login again.');
			endif;
       // }
        $ViewModel = new ViewModel(array(
			'title' => 'Log into System',
		));
		$ViewModel->setTerminal(false);
		return $ViewModel;
    }
    
    /**NDI -INTEGRATION-------------------- */
    /**WEBHOOK SERVICE FOR NDI INTEGRATION -TO LOGIN----------------------------------------------------------------------------------------------------------  */
      /**GENERATE ACCESS TOKEN TO USE THE SWAGGER APIS */
      private function authenticate($authData)
      {
          $authClient = new HttpClient();
          $authClient->setHeaders(['Content-Type' => 'application/json']);
          $authClient->setMethod(HttpRequest::METHOD_POST);
          $authClient->setUri($authData['url']);
          $authClient->setRawBody(json_encode($authData));
          $authResponse = $authClient->send();
          if ($authResponse->isSuccess()) {
              $authResult = json_decode($authResponse->getBody(), true);
              return $authResult['access_token'];
          } else {
              $error = $authResponse->getBody();
              return null;
          }
      }
     /** LOGIN VIA NDI-SCAN QR CODE SCANNER */
     public function ndiloginAction()
     {
         $accessToken = $this->authenticate($this->webhookPayload['authentication']['data']);
         if ($accessToken) {
             $webhook = $this->registerWebhook($accessToken, $this->webhookPayload);
             $responsePR = $this->proofRequest($accessToken, $this->proofRequestPayload);
             $requesturl = $responsePR['data']['proofRequestURL'];
             $reqthreadId = $responsePR['data']['proofRequestThreadId'];
			 $deeplinkURL =$responsePR['data']['deepLinkURL'];
             $this->subscribeWebhook($accessToken, $this->webhookPayload['webhookId'], $reqthreadId);
             //$studentId = $this->proofRequestPayload['proofAttributes'][0]['value'];
            // echo '<pre>'; print_r($studentId);exit;
         }
 
         $result = Builder::create()
             ->writer(new PngWriter())
             ->writerOptions([])
             ->data($requesturl)
             ->encoding(new Encoding('UTF-8'))
             ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
             ->size(185)
             ->margin(10)
             ->build();
         $qrCodeDataUri = $result->getDataUri();
 
         $ViewModel = new ViewModel([
             'title' => 'NDI-QRCODE',
             'QRcode' => $qrCodeDataUri,
             'accessToken' => $accessToken,
             'reqThreadId' => $reqthreadId,
			 'Deeplink'    =>$deeplinkURL,
         ]);
         $ViewModel->setTerminal(false);
         return $ViewModel;
     }
    /** CHECK THE STATUS FOR ISSUING */
    public function checkstatusAction()
    {
        
        $form = $this->getRequest()->getPost();
        if (empty($form)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'No POST data received'
            ]);
            exit;
        }

        $accessToken = $this->params()->fromPost('accessToken');
        $reqThreadId = $this->params()->fromPost('reqThreadId');

        if (!$accessToken || !$reqThreadId) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Missing accessToken or reqThreadId',
                'received_data' => $form
            ]);
            exit;
        }

        // Initialize variables
        $result = null;
        $status = 'Not Initialized';
        if (isset($_SESSION['reqThreadId'])) {
            unset($_SESSION['reqThreadId']);
        }
        if (!isset($_SESSION['reqThreadId'])) {
            $_SESSION['reqThreadId'] = $reqThreadId;
        }

        $client = new HttpClient();
        $client->setHeaders([
            'Authorization' => 'Bearer ' . $accessToken
        ]);
        $client->setMethod(HttpRequest::METHOD_GET);

        $maxAttempts = 10;
        $attempt = isset($_SESSION['attempt']) ? $_SESSION['attempt'] : 0;
        $success = false;

        while ($attempt < $maxAttempts && !$success) {
            $client->setUri('https://demo-client.bhutanndi.com/verifier/v1/proof-request?id=10&threadId=' . $_SESSION['reqThreadId'] . '&page=1&pageSize=10');
            try {
                $response = $client->send();
                if ($response->isSuccess()) {
                    $result = json_decode($response->getBody(), true);
                    if ($result && isset($result['data']['status'])) {
                        $status = $result['data']['status'];
                        if ($status == 'done') {
                            $success = true;
                            $this->unsubscribeWebhook($accessToken,$_SESSION['reqThreadId']);
                        } elseif($status == 'verificationFailed') {
                            $success = true;
                        }
                    }
                } else {
                    throw new Exception('HTTP request failed with status: ' . $response->getStatusCode());
                }
            } catch (Exception $e) {
                echo json_encode([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ]);
                exit;
            }

            $attempt++;
            $_SESSION['attempt'] = $attempt;
            sleep(1); // Wait for 1 second before the next attempt
        }

        if ($success || $attempt >= $maxAttempts) {
            unset($_SESSION['reqThreadId']);
            unset($_SESSION['attempt']);
            //return $this->redirect()->toRoute('application', ['action' => 'panel2']);
        }
        $_SESSION['status'] = $status;

        echo json_encode([
            'status' => $status,
            'result' => $result,
        ]);
        exit;
    }
    /**LOGIN VIA NDI-SCAN QR CODE SCANNER */
    public function issuecredentialAction()
    {
        $webhookPayload = [
            'webhookId' => 'ATHANG-DEMIS-STAGING_NDI',
            'webhookURL' => 'http://129.154.231.158/auth/webhook',
            'authentication' => [
                'type' => 'OAuth2',
                'version' => 'v1',
                'data' => [
                    'url' => 'https://staging.bhutanndi.com/authentication/v1/authenticate',
                    'grant_type' => 'client_credentials',
                    'client_id' => '3tq7ho23g5risndd90a76jre5f',
                    'client_secret' => '111rvn964mucumr6c3qq3n2poilvq5v92bkjh58p121nmoverquh',
                ]
            ]
        ];
        $proofRequestPayload = [
            'proofName' => 'Verify Foundational ID',
            'proofAttributes' => [
                [
                    'name' => 'ID Number',
                    'restrictions' => [
                        [
                            'schema_name' => 'https://dev-schema.ngotag.com/schemas/c7952a0a-e9b5-4a4b-a714-1e5d0a1ae076',
                        ]
                    ]
                ]
            ]
        ];
        $accessToken = $this->authenticate($webhookPayload['authentication']['data']);
        if ($accessToken){
            $webhook= $this->registerWebhook($accessToken, $webhookPayload);
            $responsePR= $this->proofRequest($accessToken, $proofRequestPayload);
            $requesturl = $responsePR['data']['proofRequestURL'];
            $reqthreadId = $responsePR['data']['proofRequestThreadId'];
            $deeplinkURL =$responsePR['data']['deepLinkURL'];
            //echo '<pre>';print_r($responsePR);
            $this->subscribeWebhook($accessToken,$webhookPayload['webhookId'],$reqthreadId);
        }
        $result = Builder::create()
            ->writer(new PngWriter())
            ->writerOptions([])
            ->data($requesturl)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
            ->size(185)
            ->margin(10)
            ->build();
        $qrCodeDataUri = $result->getDataUri();
        $ViewModel = new ViewModel(array(
            'title' => 'NDI-QRCODE',
            'QRcode' => $qrCodeDataUri,
            'accessToken' => $accessToken,
            'reqThreadId' => $reqthreadId,
            'Deeplink'    =>$deeplinkURL,
        ));
        $ViewModel->setTerminal(false);
        return $ViewModel;
         
    }
    /**WEBHOOK-HANDLING */
    public function webhookAction()
    {
        header('Content-Type: application/json');
        $body = file_get_contents("php://input");
        if($body){
            http_response_code(202);
        }else{
            http_response_code(400);
            return;
        }
        $presentationResult = json_decode($body, true);
        $accessToken = $this->authenticate([
            'url' => 'https://staging.bhutanndi.com/authentication/v1/authenticate',
            'grant_type' => 'client_credentials',
            'client_id' => '3tq7ho23g5risndd90a76jre5f',
            'client_secret' => '111rvn964mucumr6c3qq3n2poilvq5v92bkjh58p121nmoverquh',
        ]);
        if ($presentationResult && isset($presentationResult['type'])) {
            switch ($presentationResult['type']) {
                case 'present-proof/presentation-result':
                    if (isset($presentationResult['verification_result']) && $presentationResult['verification_result'] === 'ProofValidated') {
                        if (isset($presentationResult['requested_presentation']['revealed_attrs']['ID Number'][0]['value'])) {
                            $cid = $presentationResult['requested_presentation']['revealed_attrs']['ID Number'][0]['value'];
                            if (!empty($cid)) {
                                $schemaid = $presentationResult['requested_presentation']['identifiers'][0]['schema_id'];
                                $relationshipdid = $presentationResult['relationship_did'];
                                $holderdid = $presentationResult['holder_did'];
                                $thid = $presentationResult['thid'];
                                if ($thid && $schemaid && $relationshipdid && $holderdid) {
                                    $check_existing=$this->getDefinedTable(Administration\NdiuserTable::class)->get(array('cid'=>$cid));
                                    if($check_existing){
                                    }else{
                                        $result=$this->issueCredential($accessToken, $holderdid, $relationshipdid, $thid, $cid);
                                        $revocationID=$result['data']['revocationId'];
                                        $data = array(
                                            'cid'             => $cid,
                                            'revocation_id'   => $revocationID,
                                            'issuance_vc_date'=> date('Y-m-d'),
                                            'status'          => 1,
                                            'modified'        => date('Y-m-d H:i:s'),
                                        ); 
                                        $this->getDefinedTable(Administration\NdiuserTable::class)->save($data);
                                    }
                                    http_response_code(202);
                                }
                            }//exit;
                        }
                    }
                    break;
                case 'present-proof/rejected':
                    http_response_code(202);
                    break;
                default:
                http_response_code(202);
                    break;
            }
        }
    }
   
    /**REGISTER TO WEHOOK--------------------------------------- */
    private function registerWebhook($accessToken, $webhookPayload)
    {   
        
        $webhookClient = new HttpClient();
        $webhookClient->setHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken
        ]);
        $webhookClient->setMethod(HttpRequest::METHOD_POST);
        $webhookClient->setUri('https://demo-client.bhutanndi.com/webhook/v1/register');
        $webhookClient->setRawBody(json_encode($webhookPayload));

        $webhookResponse = $webhookClient->send();
        if ($webhookResponse->isSuccess()) {
            $result = json_decode($webhookResponse->getBody(), true);
        } else {
            $statusCode = $webhookResponse->getStatusCode();
            $error = $webhookResponse->getBody();
        }
    }
     /**SUBCRIBE TO WEHBOOK-------------------------------------------------- */
    private function subscribeWebhook($accessToken, $webhookId, $reqthreadId)
    {
        $webhookClient = new HttpClient();
        $webhookClient->setHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken
        ]);
        $webhookClient->setMethod(HttpRequest::METHOD_POST);
        $webhookClient->setUri('https://demo-client.bhutanndi.com/webhook/v1/subscribe');
        $webhookClient->setRawBody(json_encode([
            'webhookId' => $webhookId,
            'threadId' => $reqthreadId
        ]));

        $webhookResponse = $webhookClient->send();
        if ($webhookResponse->isSuccess()) {
            $result = json_decode($webhookResponse->getBody(), true);
           return $result;
        } else {
            $statusCode = $webhookResponse->getStatusCode();
            $error = $webhookResponse->getBody();
        }
    }
     /**UNSUBCRIBE FROM WEBHOOK------------------------------------- */
    
    private function unsubscribeWebhook($accessToken, $reqthreadId)
    {
        $unsubcribewebhookClient = new HttpClient();
        $unsubcribewebhookClient->setHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken
        ]);
        $unsubcribewebhookClient->setMethod(HttpRequest::METHOD_POST);
        $unsubcribewebhookClient->setUri('https://demo-client.bhutanndi.com/webhook/v1/unsubscribe');
        $unsubcribewebhookClient->setRawBody(json_encode([
            'threadId' => $reqthreadId
        ]));

        $unsubcribewebhookResponse = $unsubcribewebhookClient->send();
        if ($unsubcribewebhookResponse->isSuccess()) {
            $result = json_decode($unsubcribewebhookResponse->getBody(), true);
            //echo '<pre>'; print_r($result);
        } else {
            $statusCode = $unsubcribewebhookResponse->getStatusCode();
            $error = $unsubcribewebhookResponse->getBody();
           // echo '<pre>'; print_r($error); 
        }//exit;  
    }
      /**FOR CREDINTIALS -ISSUEING---------------------------------------------- */
     private function issueCredential($accessToken, $holderdid,$relationshipdid,$threadID,$cid)
     {
        $issuance= $this->getDefinedTable(Administration\UsersTable::class)->get(array('cid'=>$cid));
        $employee= $this->getDefinedTable(HR\EmployeeTable::class)->getEmployees(array('cid'=>$cid));
$vc = null; // Initialize to avoid undefined variable warning
        foreach($issuance as $row):
        	$vc = $row;
        endforeach;
$emp = null; // Initialize to avoid undefined variable warning
        foreach($employee as $row):
        	$emp = $row;
        endforeach;
        //error_log("reaching the issue credential issuance . $employee");
        $credentialsPayload = [
            'credentialData' => [
                'monk_id' =>   $vc['monk_id'],
                'position_title' =>$this->getDefinedTable(HR\PositiontitleTable::class)->getColumn($emp['position_title'],'position_title'),
                'location' => $this->getDefinedTable(Administration\LocationTable::class)->getColumn($emp['location'],'location'),
                'class' =>$this->getDefinedTable(Academic\StandardTable::class)->getColumn($emp['class'],'standard')
            ],
            'comment' => 'Student ID',
            'credentialType' => 'jsonld',
            'schemaId' => 'https://dev-schema.ngotag.com/schemas/3f3935de-60f1-4a60-b576-286e439388f6',
            'holderDID' => $holderdid,
            'forRelationship' => $relationshipdid,
            'threadId' => $threadID
        ];
        $credentialClient = new HttpClient();
        $credentialClient->setHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken
        ]);
        $credentialClient->setMethod(HttpRequest::METHOD_POST);
        $credentialClient->setUri('https://demo-client.bhutanndi.com/issuer/v1/issue-credential');
        $credentialClient->setRawBody(json_encode($credentialsPayload));
    
        $credentialResponse = $credentialClient->send();
        //error_log("reaching the $credentialResponse credential issuance");
        if ($credentialResponse->isSuccess()) {
            $result = json_decode($credentialResponse->getBody(), true);
            return $result;
        } else {
            $statusCode = $credentialResponse->getStatusCode();
            $error = $credentialResponse->getBody();
        }
     }
     /**
      * CREATING PROOF REQUEST-POST
      */
     public function proofRequest($accessToken, $proofRequestPayload)
     {
         $proofreqClient = new HttpClient();
         $proofreqClient->setHeaders([
             'Content-Type' => 'application/json',
             'Authorization' => 'Bearer ' . $accessToken
         ]);
         $proofreqClient->setMethod(HttpRequest::METHOD_POST);
         $proofreqClient->setUri('https://demo-client.bhutanndi.com/verifier/v1/proof-request');
         $proofreqClient->setRawBody(json_encode($proofRequestPayload));
 
         $proofreqResponse = $proofreqClient->send();
         if ($proofreqResponse->isSuccess()) {
             $result = json_decode($proofreqResponse->getBody(), true);
             return $result;
         } else {
             $statusCode = $proofreqResponse->getStatusCode();
             $error = $proofreqResponse->getBody();
         }
     }
    /**
     * Logout
     */
    public function logoutAction()
	{
        if(!$this->identity()){
	    	  $this->flashMessenger()->addMessage("warning^ Your session has already expired. Login in to proceed.");
	    	  return $this->redirect()->toRoute('auth', array('action' => 'login'));
	    }
		$auth = new AuthenticationService();
		$msg = $this->params()->fromRoute('id');
		$id = $this->identity()->id;   
		$data = array(
		    'id'          => $id,
			'last_logout' => date('Y-m-d H:i:s')	    
		); 

		$this->getDefinedTable(Administration\UsersTable::class)->save($data);

		if ($auth->hasIdentity()) {
			$identity = $auth->getIdentity();
		}			
		
		$auth->clearIdentity();
		$sessionManager = new \Laminas\Session\SessionManager();
		$sessionManager->forgetMe();
       
		if($msg == "1"):
		    $this->flashMessenger()->addMessage('warning^You cannot use the system as you are blocked. Contact the administrator.');
		else:
			$this->flashMessenger()->addMessage('info^You have successfully logged out!');
		endif;
		
		return $this->redirect()->toRoute('auth', array('action'=>'login'));
	}
    /**
	 * forgotpwd
	 */
    public function forgotpwdAction()
    {
        $captcha = new AuthForm();

        if ($this->getRequest()->isPost()) {
            $form = $this->getRequest()->getPost();
            $captcha->setData($form);
            if ($captcha->isValid()) {
                $userDtls = $this->getDefinedTable(Administration\UsersTable::class)->get(array('email' => $form['email']));
                if(sizeof($userDtls) == 0){
                    $this->flashMessenger()->addMessage('error^ This email is not registered with any of the users in the system.');
                    return $this->redirect()->toRoute('auth', array('action' => 'forgotpwd'));
                }else{
$row = null; // Initialize to avoid undefined variable warning
                    foreach ($userDtls as $temp_row):
                    	$row = $temp_row;
                    endforeach;
					$email = $row['email']; $name = $row['name'];
                    
					$expiry_time = date("Y-m-d H:i:s", strtotime('+12 hours'));
					$recovery_stamp = rtrim(strtr(base64_encode($row['email']."***".$expiry_time), '+/', '-_'), '=');
					
                    $recovery_link = "<div style='font-family: Arial, sans-serif; line-height: 19px; color: #444444; font-size: 13px; text-align: center;'>
						<a href='https://erp.bhutanpost.bt/public/auth/amendpwd/".$recovery_stamp."' style='color: #ffffff; text-decoration: none; margin: 0px; text-align: center; vertical-align: baseline; border: 4px solid #1e7e34; padding: 4px 9px; font-size: 15px; line-height: 21px; background-color: #218838;'>&nbsp; Reset Password &nbsp;</a>
					</div>";
					
                    $notify_msg = "You have requested for password recovery. Please click on password recovery link below to reset your password: <br><br>".$recovery_link.
									"<br>This link will expire in 12 hours and can be used only once.<br><br>If you do not want to change your password and did not request this, please ignore and delete this message.";
                    $mail = array(
                        'email'    => $row['email'],
                        'name'     => $row['name'],
                        'subject'  => 'BhutanPost-ERP: Password Recovery', 
                        'message'  => $notify_msg,
                        'cc_array' => [],
                    );
                    $this->EmailPlugin()->sendmail($mail);
					$this->flashMessenger()->addMessage("success^ Your password reset link will be sent to your registered email, i.e. ".$row['email'].". Please check in the spam folder if you can't find in the inbox. Thank You.");
					return $this->redirect()->toRoute('auth', array('action' => 'forgotpwd'));
                    
                }
            }else{
                $this->flashMessenger()->addMessage("warning^ Captcha is invalid. Try again.");
                return $this->redirect()->toRoute('auth', array('action' => 'forgotpwd'));
            }
        }
        $ViewModel = new ViewModel(array('title' => 'Forgot Password','captcha'=>$captcha));
        $ViewModel->setTerminal(false);
        return $ViewModel;
    }
    /**
     * amendpwd Action -- link from email
     */
    public function amendpwdAction()
    {	
		$recovery_dtl = $this->params()->fromRoute('id');
		$decoded_dtl = base64_decode(str_pad(strtr($recovery_dtl, '-_', '+/'), 4 - ((strlen($recovery_dtl) % 4) ?: 4), '=', STR_PAD_RIGHT));
		$array_dtl = explode("***", $decoded_dtl);
		$email = (sizeof($array_dtl)>1)?$array_dtl[0]:'0';
		$expiry_time = (sizeof($array_dtl)>1)?$array_dtl[1]:'0';
		$userDtls = $this->getDefinedTable(Administration\UsersTable::class)->get(array('email' => $email));
		
        if($this->getRequest()->isPost()) {
            $form = $this->getRequest()->getPost();
			$staticSalt = $this->password()->getStaticSalt();
			$user_dtls = $this->getDefinedTable(Administration\UsersTable::class)->get(array('email' => $form['recovery_id']));	
			if(sizeof($user_dtls) == 1):
	$user_dtl = null; // Initialize to avoid undefined variable warning
			foreach($user_dtls as $row):
				$user_dtl = $row;
			endforeach;
				if($user_dtl['email'] == $form['recovery_id']):
					if($form['new_password'] == $form['confirm_password']):
						$dynamicSalt = $this->password()->generateDynamicSalt();
						$password = $this->password()->encryptPassword(
								$staticSalt,
								$form['new_password'],
								$dynamicSalt
						);
						$data = array(
								'id'		=> $user_dtl['id'],
								'password'	=> $password,
								'salt'		=> $dynamicSalt,
						);
						$result = $this->getDefinedTable(Administration\UsersTable::class)->save($data);
						if($result > 0):	
							$this->flashMessenger()->addMessage("success^ Successfully updated user password.");
						else:
							$this->flashMessenger()->addMessage("error^ Failed to update user password.");
						endif;
					else:
						$this->flashMessenger()->addMessage("error^ New Password and Confirmed Password doesn't match.");
					endif;
				else:
					$this->flashMessenger()->addMessage("error^ The entered email and the recovery details doesn't match.");
				endif;
			else:
				$this->flashMessenger()->addMessage("error^ The user with following recovery details doesn't exist anymore in the system.");
			endif;
			return $this->redirect()->toRoute('auth', array('action' => 'login'));
        }
		if($expiry_time < date('Y-m-d H:i:s')){
			$this->flashMessenger()->addMessage('error^ This password recovery link has already expired.');
			return $this->redirect()->toRoute('auth', array('action' => 'login'));
		}else{
			if(sizeof($userDtls) == 0){
				$this->flashMessenger()->addMessage('error^ This email is no more associated with any of the users in the system.');
				return $this->redirect()->toRoute('auth', array('action' => 'login'));
			}else{
$row = null; // Initialize to avoid undefined variable warning
				foreach ($userDtls as $temp_row):
					$row = $temp_row;
				endforeach;
				$email = $row['email'];
				$ViewModel = new ViewModel(array('title' => 'Amend Password','email' => $email,));
				$ViewModel->setTerminal(false);
				return $ViewModel;
			}
		}
		return $this->redirect()->toRoute('auth', array('action' => 'login'));
    }
}