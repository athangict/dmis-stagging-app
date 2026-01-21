<?php
namespace Organization\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Organization\Model As Organization;
use Acl\Model As Acl;
use Administration\Model As Administration;
class FacilityController extends AbstractActionController
{   
	private $_container;
	protected $_table; 		// database table 
    protected $_user; 		// user detail
    protected $_login_id; 	// logined user id
    protected $_login_role; // logined user role
    protected $_author; 	// logined user id
    protected $_created; 	// current date to be used as created dated
    protected $_modified; 	// current date to be used as modified date
    protected $_config; 	// configuration details
    protected $_dir; 		// default file directory
    protected $_id; 		// route parameter id, usally used by crude
    protected $_auth; 		// checking authentication
    protected $_safedataObj; // safedata controller plugin
	protected $_permission; // permission plugin
	protected $_connection; // database connection
	protected $_permissionObj; // permission plugin object
	protected $_highest_role; // highest user role
	protected $_lowest_role; // lowest user role

	
	public function __construct(ContainerInterface $container)
    {
        $this->_container = $container;
    }
	/**
	 * Laminas Default TableGateway
	 * Table name as the parameter
	 * returns obj
	 */
	public function getDefaultTable($table)
	{
		$this->_table = new TableGateway($table, $this->_container->get('Laminas\Db\Adapter\Adapter'));
		return $this->_table;
	}

	/**
	 * User defined Model
	 * Table name as the parameter
	 * returns obj
	 */
	  public function getDefinedTable($table)
    {
        $definedTable = $this->_container->get($table);
        return $definedTable;
    }
    /**
     * initial set up
     * general variables are defined here
     */
	public function init()
	{
		$this->_auth = new AuthenticationService;
		if(!$this->_auth->hasIdentity()):
			$this->flashMessenger()->addMessage('error^ You dont have right to access this page!');
			$this->redirect()->toRoute('auth', array('action' => 'login'));
		endif;
		if(!isset($this->_config)) {
			$this->_config = $this->_container->get('Config');
		}
		if(!isset($this->_user)) {
			$this->_user = $this->identity();
		}
		if(!isset($this->_login_id)){
			$this->_login_id = $this->_user->id;  
		}
		if(!isset($this->_login_role)){
			$this->_login_role = $this->_user->role;  
		}
		if(!isset($this->_highest_role)){
			$this->_highest_role = $this->getDefinedTable(Acl\RolesTable::class)->getMax('id'); 	
		}
		if(!isset($this->_lowest_role)){
			$this->_lowest_role = $this->getDefinedTable(Acl\RolesTable::class)->getMin('id'); 
		}

		if(!isset($this->_author)){
			$this->_author = $this->_user->id;  
		}

		$this->_id = $this->params()->fromRoute('id');

		$this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');

		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

	}
	
	/** xxxxxxxxxxxxxxxxxxxxxxxxxx---------- FACILITY INDEX-------------------xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx */
	/** INDEX ACTION - Default landing page for facility --------------------------------------------------------------------------------------*/
	public function indexAction()
	{
		$this->init();
		$financialTable = $this->getDefinedTable(Organization\FinancialTable::class)->getAll(); 
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($financialTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(20);
		$paginator->setPageRange(8);
		return new ViewModel(array(
			'title'            => 'Facility - Financial',
			'paginator'        => $paginator,
			'page'             => $page,
			'locationObj'      => $this->getDefinedTable(Administration\LocationTable::class),
			'organizationObj'  => $this->getDefinedTable(Administration\LocationTable::class),
		)); 
	}
	
	/** xxxxxxxxxxxxxxxxxxxxxxxxxx---------- FINANCIAL FACILITY-------------------xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx */
	/** FINANCIAL INCOME & EXPENSES--------------------------------------------------------------------------------------*/
	public function financialAction()
	{
		$this->init();
		$financialTable = $this->getDefinedTable(Organization\FinancialTable::class)->getAll(); 
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($financialTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(20);
		$paginator->setPageRange(8);
        return new ViewModel(array(
			'title'            => 'Financial',
			'paginator'        => $paginator,
			'page'             => $page,
			'locationObj'      => $this->getDefinedTable(Administration\LocationTable::class),
			'organizationObj'  => $this->getDefinedTable(Administration\LocationTable::class),
		)); 
	}
	 /**GET ORGANIZATION FUNCTION_---------------------------------------------------------------------------**/
	public function getorganizationAction()
	{
		$this->init();
		$lc='';
		$form = $this->getRequest()->getPost();
		
		$location_id = $form['location_id'];
		//$region_id =1;
		$orgs = $this->getDefinedTable(Administration\LocationTable::class)->get(array('organization_level' => $location_id));
		
		$lc.="<option value=''></option>";
		foreach($orgs as $org):
			$lc.= "<option value='".$org['id']."'>".$org['location']."</option>";
		endforeach;
		echo json_encode(array(
			'org' => $lc,
		));
		exit;
	}
	/**ADD FINANCIAL --------------------------------------------------------------------------------------------------*/
	public function addfinancialAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'location'        => $form['location'],
				'organization'    => $form['organization'],
				'financial_desp'  => $form['financial_desp'],
				'total_income'    => $form['income'],
				'total_expense'   => $form['expense'],
				'author'          =>$this->_author,
				'created'         =>$this->_created,
				'modified'        =>$this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Organization\FinancialTable::class)->save($data);
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ New Bank reference type successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add new Financial");
			endif;
			return $this->redirect()->toRoute('fac', array('action'=>'financial'));
		}
		$ViewModel = new ViewModel(array(
			'title'         =>'Add Financial',
			'financial'     => $this->getDefinedTable(Organization\FinancialTable::class)->getAll(),
			'locations'     => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/** EDIT FINANCIAL------------------------------------------------------------------------------------------- */
	public function editfinancialAction()
	{
		$this->init();
		$id = $this->_id;
		$array_id = explode("_", $id);
		$financial_id = $array_id[0];
		$page = (sizeof($array_id)>1)?$array_id[1]:'';
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'id'              => $form['financial_id'],
				'location'        => $form['location'],
				'organization'    => $form['organization'],
				'financial_desp'  => $form['financial_desp'],
				'total_income'    => $form['income'],
				'total_expense'   => $form['expense'],
				'author' =>$this->_author,
				'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result=$this->getDefinedTable(Organization\FinancialTable::class)->save($data);
			if($result > 0):
			$this->flashmessenger()->addMessage("success^ Financial Information successfully updated ");
			else:
			$this->flashmessenger()->addMessage("error^ Failed to update Financial Information");
			endif;
			return $this->redirect()->toRoute('fac', array('action'=>'financial'));
		}
		$ViewModel = new ViewModel(array(
			'title'            =>'Edit Financail',
			'financial'        => $this->getDefinedTable(Organization\FinancialTable::class)->get($this->_id),
			'financials'       => $this->getDefinedTable(Organization\FinancialTable::class)->getAll(),
			'locations'        => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'organizations'    => $this->getDefinedTable(Administration\DepartmentTable::class)->getAll(),
			'organizationObj'  =>$this->getDefinedTable(Administration\LocationTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/** xxxxxxxxxxxxxxxxxxxxxxxxxx---------- CONNECTIVITY FACILITY-------------------xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx */
	/** CONNECTIVITY--------------------------------------------------------------------------------------*/
	public function connectivityAction()
	{
		$this->init();
		$connectivityTable = $this->getDefinedTable(Organization\ConnectivityTable::class)->getAll(); 
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($connectivityTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(20);
		$paginator->setPageRange(8);
        return new ViewModel(array(
			'title'            => 'Connectivity',
			'paginator'        => $paginator,
			'page'             => $page,
			'locationObj'      => $this->getDefinedTable(Administration\LocationTable::class),
			'organizationObj'  => $this->getDefinedTable(Administration\DepartmentTable::class),
		)); 
	}
	/**ADD CONNECTIVITY --------------------------------------------------------------------------------------------------*/
	public function addconnectivityAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'location'                => $form['location'],
				'organization'            => $form['organization'],
				'road_connectivity'       => $form['road'],
				'road_distance'           => $form['estimated_distance'],
				'internet_connectivity'   => $form['internet'],
				'accssible_to'            => $form['accssible_to'],
				'electricity_connectivity'=> $form['electricity'],
				'author'                  =>$this->_author,
				'created'                 =>$this->_created,
				'modified'                =>$this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Organization\ConnectivityTable::class)->save($data);
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ New Connectivity successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add Connectivity");
			endif;
			return $this->redirect()->toRoute('fac', array('action'=>'connectivity'));
		}
		$ViewModel = new ViewModel(array(
			'title'         =>'Add Connectivity',
			'connectivity'  => $this->getDefinedTable(Organization\ConnectivityTable::class)->getAll(),
			'locations'     => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/** EDIT CONNECTIVITY------------------------------------------------------------------------------------------- */
	public function editconnectivityAction()
	{
		$this->init();
		$id = $this->_id;
		$array_id = explode("_", $id);
		$connectivity_id = $array_id[0];
		$page = (sizeof($array_id)>1)?$array_id[1]:'';
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'id'                      => $form['connectivity_id'],
				'location'                => $form['location'],
				'organization'            => $form['organization'],
				'road_connectivity'       => $form['road'],
				'road_distance'           => $form['estimated_distance'],
				'internet_connectivity'   => $form['internet'],
				'accssible_to'            => $form['accssible_to'],
				'electricity_connectivity'=> $form['electricity'],
				'author'                  =>$this->_author,
				'modified'                =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result=$this->getDefinedTable(Organization\ConnectivityTable::class)->save($data);
			if($result > 0):
			$this->flashmessenger()->addMessage("success^ Connectivity successfully updated ");
			else:
			$this->flashmessenger()->addMessage("error^ Failed to update Connectivity Information");
			endif;
			return $this->redirect()->toRoute('fac', array('action'=>'connectivity'));
		}
		$ViewModel = new ViewModel(array(
			'title'         =>'Edit Connectivity',
			'connectivity'     => $this->getDefinedTable(Organization\ConnectivityTable::class)->get($this->_id),
			'connectivities'   => $this->getDefinedTable(Organization\FinancialTable::class)->getAll(),
			'locations'        => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'organizations'    => $this->getDefinedTable(Administration\DepartmentTable::class)->getAll(),
			'organizationObj'  =>$this->getDefinedTable(Administration\LocationTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/** xxxxxxxxxxxxxxxxxxxxxxxxxx---------- WASH FACILITY-------------------xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx */
	/** WASH--------------------------------------------------------------------------------------*/
	public function washAction()
	{
		$this->init();
		$washTable = $this->getDefinedTable(Organization\WashTable::class)->getAll(); 
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($washTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(20);
		$paginator->setPageRange(8);
        return new ViewModel(array(
			'title'            => 'Connectivity',
			'paginator'        => $paginator,
			'page'             => $page,
			'locationObj'      => $this->getDefinedTable(Administration\LocationTable::class),
			'organizationObj'  => $this->getDefinedTable(Administration\LocationTable::class),
		)); 
	}
	/**ADD WASH --------------------------------------------------------------------------------------------------*/
	public function addwashAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'location'                => $form['location'],
				'organization'            => $form['organization'],
				'water_facility'          => $form['water'],
				'water_facility1'         => $form['water1'],
				'sanitation_facility'     => $form['sanitation'],
				'sanitation_facility1'    => $form['sanitation1'],
				'hygiene_facility'        => $form['hygiene'],
				'hygiene_facility1'       => $form['hygiene1'],
				'author'                  =>$this->_author,
				'created'                 =>$this->_created,
				'modified'                =>$this->_modified,
			);
			// echo '<pre>';print_r($data);exit;
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Organization\WashTable::class)->save($data);
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ New wash successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add wash");
			endif;
			return $this->redirect()->toRoute('fac', array('action'=>'wash'));
		}
		$ViewModel = new ViewModel(array(
			'title'         =>'Add Wash',
			'wash'          => $this->getDefinedTable(Organization\WashTable::class)->getAll(),
			'locations'     => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/** EDIT WASH------------------------------------------------------------------------------------------- */
	public function editwashAction()
	{
		$this->init();
		$id = $this->_id;
		$array_id = explode("_", $id);
		$connectivity_id = $array_id[0];
		$page = (sizeof($array_id)>1)?$array_id[1]:'';
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'id'                      => $form['wash_id'],
				'location'                => $form['location'],
				'organization'            => $form['organization'],
				'water_facility'          => $form['water'],
				'water_facility1'         => $form['water1'],
				'sanitation_facility'     => $form['sanitation'],
				'sanitation_facility1'    => $form['sanitation1'],
				'hygiene_facility'        => $form['hygiene'],
				'hygiene_facility1'        => $form['hygiene1'],
				'author'                  =>$this->_author,
				'modified'                =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result=$this->getDefinedTable(Organization\WashTable::class)->save($data);
			if($result > 0):
			$this->flashmessenger()->addMessage("success^ Wash successfully updated ");
			else:
			$this->flashmessenger()->addMessage("error^ Failed to update Wash Information");
			endif;
			return $this->redirect()->toRoute('fac', array('action'=>'wash'));
		}
		$ViewModel = new ViewModel(array(
			'title'         =>'Edit Wash',
			'wash'             => $this->getDefinedTable(Organization\WashTable::class)->get($this->_id),
			'washs'            => $this->getDefinedTable(Organization\WashTable::class)->getAll(),
			'locations'        => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'organizations'    => $this->getDefinedTable(Administration\DepartmentTable::class)->getAll(),
			'organizationObj'  =>$this->getDefinedTable(Administration\LocationTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
}

