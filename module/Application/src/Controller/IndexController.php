<?php
/**
 * chophel@athang.com
 * @see       https://github.com/laminas/laminas-mvc-skeleton for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-skeleton/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-skeleton/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Interop\Container\ContainerInterface;
use Laminas\Authentication\AuthenticationService;
use Acl\Model as Acl;
use Academic\Model As Academic;
use Hr\Model As Hr;
use Administration\Model as Administration;

class IndexController extends AbstractActionController
{
    private   $_container;
	protected $_table; 		// database table 
    protected $_user; 		// user detail
    protected $_login_id; 	// logined user id
    protected $_login_role; // logined user role
	protected $_login_location; // logined user location
    protected $_author; 	// logined user id
    protected $_created; 	// current date to be used as created dated
    protected $_modified; 	// current date to be used as modified date
    protected $_config; 	// configuration details
    protected $_dir; 		// default file directory
    protected $_id; 		// route parameter id, usally used by crude
    protected $_auth; 		// checking authentication
    protected $_highest_role;// highest user role
    protected $_lowest_role;// loweset user role
    protected $_permission; // permission object
    protected $_permissionObj; // permission plugin instance

    public function __construct(ContainerInterface $container)
    {
        $this->_container = $container;
    }
	/**
	 * Zend Default TableGateway
	 * Table name as the parameter
	 * returns obj
	 */
	public function getDefaultTable($table)
	{
		$this->_table = new TableGateway($table, $this->_container->get('Laminas\Db\Adapter\Adapter'));
		return $this->_table;
	}
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
		if(!isset($this->_login_location)){
			$this->_login_location = $this->_user->location; 
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

		$this->_permissionObj =  $this->PermissionPlugin();
		$this->_permission = $this->_permissionObj->permission($this->getEvent());
	}

    public function indexAction()
    {
        $this->init();
		
		switch($this->_login_role){
			case 2:
				case 3: return $this->redirect()->toRoute('application', array('action'=>'panel3'));
				break;
					case 4: 
					
			case 5: return $this->redirect()->toRoute('application', array('action'=>'panel2'));
			        break;
				case 6:
					case 7:
						case 8: return $this->redirect()->toRoute('application', array('action'=>'panel1'));
						break;
			case 99:
				case 100: return $this->redirect()->toRoute('application', array('action'=>'panel1'));
						break;
			default: return $this->redirect()->toRoute('application', array('action'=>'panel1'));
					break;
		}
    }
	/**
	 * Admin Panel -- Administrator & System Manager
	 */
	public function panel1Action()
	{
		$this->init();
		$userid= $this->_user->id;  
		$location_from_user =$this->getDefinedTable(Administration\UsersTable::class)->getColumn($userid,'location');
		$org_level =$this->getDefinedTable(Administration\LocationTable::class)->getColumn($location_from_user,'organization_level');
		$standard= $this->getDefinedTable(Academic\StandardTable::class)->get(array('level' =>$org_level));
	    $date= date('Y-m-d');
		$date_object= strtotime($date);
		$year = date("Y", $date_object);
       // $distinctyear= $this->getDefinedTable(Academic\ResultTable::class)->getDistinctyear($year);
		return new ViewModel(array(
			'title'              => 'Admin-Dashboard-Panel',
			'user_location'      =>$location_from_user,
			'distinctyear'      =>$year,
            'org_level'         => $org_level,
			'studentObj'         =>$this->getDefinedTable(Academic\StudentTable::class), 
			'resultObj'          =>$this->getDefinedTable(Academic\ResultTable::class),
			'locationObj'        =>$this->getDefinedTable(Administration\LocationTable::class),
			'employeeObj'        =>$this->getDefinedTable(Hr\EmployeeTable::class),
			'standardObj'        =>$this->getDefinedTable(Academic\StandardTable::class), 
			'organizationObj'    =>$this->getDefinedTable(Administration\LocationTable::class),
			'positiontitleObj'   =>$this->getDefinedTable(Hr\PositiontitleTable::class),
		));
	}
	/**
	 * Admin Panel -- Administrator & System Manager
	 */
	public function panel2Action()
	{
		$this->init();
		$userid= $this->_user->id;  
		$location_from_user =$this->getDefinedTable(Administration\UsersTable::class)->getColumn($userid,'location');
		$org_level =$this->getDefinedTable(Administration\LocationTable::class)->getColumn($location_from_user,'organization_level');
		$standard= $this->getDefinedTable(Academic\StandardTable::class)->get(array('level' =>$org_level));
	    $date= date('Y-m-d');
		$date_object= strtotime($date);
		$year = date("Y", $date_object);
		return new ViewModel(array(
			'title'              => 'Dashboard-Panel-2',
			'user_location'      =>$location_from_user,
			'distinctyear'      =>$year,
            'org_level'         => $org_level,
			'studentObj'         =>$this->getDefinedTable(Academic\StudentTable::class), 
			'resultObj'          =>$this->getDefinedTable(Academic\ResultTable::class),
			'locationObj'        =>$this->getDefinedTable(Administration\LocationTable::class),
			//'employeeObj'      =>$this->getDefinedTable(Hr\EmployeeTable::class),
			'standardObj'        =>$this->getDefinedTable(Academic\StandardTable::class), 
			'organizationObj'    =>$this->getDefinedTable(Administration\LocationTable::class),
		));
	}
	/**
	 * M&E Panel 
	 */
	public function panel3Action()
	{
		$this->init();
		$userid= $this->_user->id;  
		$location_from_user =$this->getDefinedTable(Administration\UsersTable::class)->getColumn($userid,'location');
		$org_level =$this->getDefinedTable(Administration\LocationTable::class)->getColumn($location_from_user,'organization_level');
		$standard= $this->getDefinedTable(Academic\StandardTable::class)->get(array('level' =>$org_level));
	    $date= date('Y-m-d');
		$date_object= strtotime($date);
		$year = date("Y", $date_object);
		return new ViewModel(array(
			'title'              => 'Dashboard-Panel-3',
			'user_location'      =>$location_from_user,
			'distinctyear'       =>$year,
            'org_level'          => $org_level,
			'studentObj'         =>$this->getDefinedTable(Academic\StudentTable::class), 
			'resultObj'          =>$this->getDefinedTable(Academic\ResultTable::class),
			'locationObj'        =>$this->getDefinedTable(Administration\LocationTable::class),
			'classstdObj'        =>$this->getDefinedTable(Academic\ClassStudentTable::class),
			'sectionObj'         =>$this->getDefinedTable(Academic\SectionTable::class),
			'standardObj'        =>$this->getDefinedTable(Academic\StandardTable::class), 
			'organizationObj'    =>$this->getDefinedTable(Administration\LocationTable::class),
		));
	}
	/**
	 * User Panel
	 */
	public function panel4Action()
	{
		$this->init();
		return new ViewModel(array(
			'title'             => 'Dashboard-Panel-4',
		));
	}
	/**
	 * Activity Log
	 */
	public function activitylogAction()
	{
		$this->init();
		
		$id = $this->params()->fromRoute('id');	
		$params = explode("-", $id);
		$process = $params['0'];
		$process_id = $params['1'];		
		$activitylogs = $this->getDefinedTable(Acl\ActivityLogTable::class)->get(array('process'=>$process, 'process_id'=>$process_id));		
		
		$viewModel =  new ViewModel(array(
				'title'        => 'Activity Logs',
				'activitylogs' => $activitylogs,
				'usersObj'     => $this->getDefinedTable(Administration\UsersTable::class),
				'roleObj'      => $this->getDefinedTable(Acl\RolesTable::class),
		));
		$viewModel->setTerminal('false');
		return $viewModel;
	}
	/**
	 * Documentation
	 * User Manual
	 */
	public function documentationAction()
	{
		$this->init();
		return new ViewModel(array(
			'title' => 'Documentation',
		));
	}
	/**
	 *  DITT API
	 */
	public function censusAction()
	{
		$this->init();
		
		if(!$this->_auth->hasIdentity()):
			$this->flashMessenger()->addMessage('error^ You dont have right to access this page!');
         	$this->redirect()->toRoute('auth', array('action' => 'login'));
		endif;
		$census_records = array();
		$family_records = array();
		if($this->getRequest()->isPost()){	
			$form = $this->getRequest()->getPost();
			
			$cid_no = $form['cidnumber'];
		    $url = $this->_config['ditt_api_census'];
			$census_url = $url."citizenAPI/index.php";
			
			$data = array(
				'cid' => $cid_no,
			);
			
			$census_records = $this->ApiPlugin()->sendApiData($census_url,$data);
			
			$census_record = null; // Initialize to avoid undefined variable warning
		foreach($census_records as $row):
			$census_record = $row;
		endforeach;
			//echo "<pre>";print_r($census_record);
			
			$url = $this->_config['ditt_api_census'];
			$houseHoldNum = $census_record['householdNo'];
		    $family_url = $url."familyAPI/index.php";
			$family_data = array(
				'house_hold_no' => $houseHoldNum,
			);
			
			$family_records = $this->ApiPlugin()->sendApiData($family_url,$family_data);
			//echo "<pre>";print_r($family_records);exit;
		}
		return new ViewModel(array(
			'title'	=> 'Check Census',
			'censusDetails' => $census_records,
			'familyDetails' => $family_records,
		));
	}
}
