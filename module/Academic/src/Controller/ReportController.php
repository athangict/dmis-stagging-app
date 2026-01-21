<?php
namespace Academic\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Academic\Model As Academic;
use Hr\Model As Hr;

class ReportController extends AbstractActionController
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
	protected $_permission; // permission plugin
	protected $_highest_role; // highest user role
	protected $_lowest_role; // lowest user role
	protected $_safedataObj; // safedata object
	protected $_connection; // database connection
	protected $_permissionObj; // permission plugin object
    
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

		$this->_permissionObj =  $this->PermissionPlugin();
		$this->_permission = $this->_permissionObj->permission($this->getEvent());
	}
	/**
	 *  index action
	 */
	public function indexAction()
	{
		$this->init();
		return new ViewModel(array(
			'title' => 'Index',
		));	
	}
	/**
	 *  Result Report action
	 */
	public function resultreportAction()
	{  
    	$this->init(); 
		$id = $this->_id;
			$array_id = explode("_", $id);
			$year = $array_id[0];
			$organization = (sizeof($array_id)>1)?$array_id[1]:'';
			$class = (sizeof($array_id)>2)?$array_id[2]:'';
			if($this->getRequest()->isPost())
			{
				$form      			= $this->getRequest()->getPost();
				$organization  		= $form['organization'];
				$class        		= $form['classes'];
				$year        		= $form['year'];
				
			}else{
				$organization       = $organization;
				$class          	= $class;
				$year      			= date('Y');
			}
			$data = array(
				'organization'  	=> $organization,
				'class'  			=> $class,
				'year'  			=> $year,
			);
			//print_r($data);exit;
		return new ViewModel(array(
			'title' => 'Class',
			'data'      => $data,
			'studentObj' => $this->getDefinedTable(Academic\ResultTable::class),
			'classes' => $this->getDefinedTable(Academic\ClassTable::class),
			'employee' => $this->getDefinedTable(Hr\EmployeeTable::class),
			'organization' => $this->getDefinedTable(Administration\DepartmentTable::class),
			'result'=>$this->getDefinedTable(Academic\ResultTable::class)->getAllReport($data,'e.std_id'),
       ));	
	}
	/**
	 *  View Single Result action
	 */
	public function viewsingleresultAction()
	{  
    	$this->init(); 
		
		return new ViewModel(array(
			'title' => 'Sinle Report',
			'student'=>$this->getDefinedTable(Academic\ResultTable::class)->get(array('std_id'=>$this->_id)),
			'marks'=>$this->getDefinedTable(Academic\ResultTable::class),
			'subject'=>$this->getDefinedTable(Academic\SubjectTable::class),
			'personal'=>$this->getDefinedTable(Academic\ResultTable::class),
			'dzongkhagObj'=>$this->getDefinedTable(Administration\DistrictTable::class),
			'gewogObj'=>$this->getDefinedTable(Administration\BlockTable::class),
			'villageObj'=>$this->getDefinedTable(Administration\VillageTable::class),
			'classObj'=>$this->getDefinedTable(Academic\ClassTable::class),
			'employeeObj'=>$this->getDefinedTable(Hr\EmployeeTable::class),
		));	
	}
	/*
     * Return Distinct value of the column
	 * @param Array $where
	 * @param String $column
	 * @return Array | Int
	 */
	public function getDistinct($column,$where = NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'distinct' => new Expression('DISTINCT('.$column.')')
		));
		if($where!=NULL){
			$select->where($where);
		}
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		$column = array();
		foreach ($results as $result):
			array_push($column,$result['distinct']);
		endforeach;
	
		return $column;
	}
  
}
?>

