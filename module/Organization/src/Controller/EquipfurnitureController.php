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

class EquipfurnitureController extends AbstractActionController
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
    /** xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx------------ACTIONS--------------xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx*/
	/** EQUIPMENT CRUD-------------------------------------------------------------------------------------*/
	
	 public function equipmentAction()
	 {
		 $this->init();
		 $equipTable = $this->getDefinedTable(Organization\EquipmentTable::class)->getAll();
		 $paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($equipTable));
			 
		 $page = 1;
		 if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		 $paginator->setCurrentPageNumber((int)$page);
		 $paginator->setItemCountPerPage(20);
		 $paginator->setPageRange(8);
		 return new ViewModel(array(
			'title'            => 'Equipment',
			'paginator'        => $paginator,
			'page'             => $page,
			'equipmenttypeObj' => $this->getDefinedTable(Organization\EquipmentmasterTable::class),
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
	 /** ADD EQUIPMENT-------------------------------------------------------------------------------------------  */
	 public function addequipmentAction()
	 {
		 $this->init();
		 if($this->getRequest()->isPost()){
			 $form = $this->getRequest()->getPost();
			 $data = array(
				'location'       => $form['location'],
				'organization'   => $form['organization'],
				'equipment_type' => $form['type'],
				'equipment_name' => $form['equip'],
				'usable'         => $form['usable'],
				'not_usable'     => $form['notusable'],
				'status'         =>1,
				'author'         =>$this->_author,
				'created'        =>$this->_created,
				'modified'       =>$this->_modified,
			 );
			 $data = $this->_safedataObj->rteSafe($data);
			 $result = $this->getDefinedTable(Organization\EquipmentTable::class)->save($data);
	 
			 if($result > 0):
				 $this->flashMessenger()->addMessage("success^ New currency successfully added");
			 else:
				 $this->flashMessenger()->addMessage("Failed^ Failed to add new currency");
			 endif;
			 return $this->redirect()->toRoute('ef', array('action' => 'equipment'));
		 }
		 $ViewModel = new ViewModel([
			'title'              => 'Add Equipment',
			'equipmenttypes'     => $this->getDefinedTable(Organization\EquipmentmasterTable::class)->getAll(),
			'locations'          => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
		 ]);
		 $ViewModel->setTerminal(True);
		 return $ViewModel;
	 }
	 /**EDIT EQUIPMENT ------------------------------------------------------------------------------------------ */
	 public function editequipmentAction()
	 {
		 $this->init();
		 $id = $this->_id;
		 $array_id = explode("_", $id);
		 $equipment_id = $array_id[0];
		 $page = (sizeof($array_id)>1)?$array_id[1]:'';
		 
		 if($this->getRequest()->isPost()){
			 $form = $this->getRequest()->getPost();
			 $data = array(  
				 'id'                 => $form['equipment_id'],
				 'location'           => $form['location'],
				 'organization'       => $form['organization'],
				 'equipment_type'     => $form['type'],
				 'equipment_name'     => $form['equip'],
				 'usable'             => $form['usable'],
				 'not_usable'         => $form['notusable'],
				 'status'             => 1,
				 'author'             => $this->_author,
				 'modified'           => $this->_modified,
			 );
			 $data = $this->_safedataObj->rteSafe($data);
			// echo '<pre>';print_r($data);exit;
			 $this->_connection->beginTransaction();
			 $result = $this->getDefinedTable(Organization\EquipmentTable::class)->save($data);
			 if($result > 0):
				 $this->_connection->commit();
				 $this->flashMessenger()->addMessage("success^ successfully edited Equipment");
			 else:
				 $this->_connection->rollback();
				 $this->flashMessenger()->addMessage("error^ Failed to edit Equipment");
			 endif;
			 return $this->redirect()->toRoute('ef', array('action'=>'equipment'));
		 }		
		 $ViewModel = new ViewModel([
			'title'            => 'Edit Equipment',
			'page'             => $page,
			'equipment'        => $this->getDefinedTable(Organization\EquipmentTable::class)->get($equipment_id),
			'equipmenttypes'   => $this->getDefinedTable(Organization\EquipmentmasterTable::class)->getAll(),
			'locations'        => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'organizations'    => $this->getDefinedTable(Administration\DepartmentTable::class)->getAll(),
			'organizationObj'  =>$this->getDefinedTable(Administration\LocationTable::class),
		 ]);
		 $ViewModel->setTerminal(True);
		 return $ViewModel;
	 }
	/** FURNITURE CRUD--------------------------------------------------------------------------------------------*/
	 
	  public function furnitureAction()
	  {
		  $this->init();
		  $furTable = $this->getDefinedTable(Organization\FurnitureTable::class)->getAll();
		  $paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($furTable));
			  
		  $page = 1;
		  if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		  $paginator->setCurrentPageNumber((int)$page);
		  $paginator->setItemCountPerPage(20);
		  $paginator->setPageRange(8);
		  return new ViewModel(array(
			  'title'            => 'Furniture',
			  'paginator'        => $paginator,
			  'page'             => $page,
			  'furnituretypeObj' => $this->getDefinedTable(Organization\FurnituremasterTable::class),
			  'locationObj'      => $this->getDefinedTable(Administration\LocationTable::class),
			  'organizationObj'  => $this->getDefinedTable(Administration\LocationTable::class),
		  )); 
	  }
	  /** ADD FURNITURE--------------------------------------------------------------------------------------------------------- */
	  public function addfurnitureAction()
	  {
		  $this->init();
		  if($this->getRequest()->isPost()){
			  $form = $this->getRequest()->getPost();
			  $data = array(
				'location'       => $form['location'],
				'organization'   => $form['organization'],
				'furniture_type' => $form['type'],
				'furniture_name' => $form['fur'],
				'usable'         => $form['usable'],
				'not_usable'     => $form['notusable'],
				'status'         =>1,
				'author'         =>$this->_author,
				'created'        =>$this->_created,
				'modified'       =>$this->_modified,
			  );
			  $data = $this->_safedataObj->rteSafe($data);
			  $result = $this->getDefinedTable(Organization\FurnitureTable::class)->save($data);
	  
			  if($result > 0):
				  $this->flashMessenger()->addMessage("success^ New currency successfully added");
			  else:
				  $this->flashMessenger()->addMessage("Failed^ Failed to add new Furniture");
			  endif;
			  return $this->redirect()->toRoute('ef', array('action' => 'furniture'));
		  }
		  $ViewModel = new ViewModel([
			'title'              => 'Add Equipment',
			'furnituretypes'     => $this->getDefinedTable(Organization\FurnituremasterTable::class)->getAll(),
			'locations'          => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
		 ]);
		 $ViewModel->setTerminal(True);
		 return $ViewModel;
	  }
	  /**
	   *  Edit Currency action
	   */
	  public function editfurnitureAction()
	  {
		  $this->init();
		  $id = $this->_id;
		  $array_id = explode("_", $id);
		  $furniture_id = $array_id[0];
		  $page = (sizeof($array_id)>1)?$array_id[1]:'';
		  
		  if($this->getRequest()->isPost()){
			  $form = $this->getRequest()->getPost();
			  $data = array(  
				'id'                 => $form['furniture_id'],
				'location'           => $form['location'],
				'organization'       => $form['organization'],
				'furniture_type'     => $form['type'],
				'furniture_name'     => $form['fur'],
				'usable'             => $form['usable'],
				'not_usable'         => $form['notusable'],
				'status'             =>1,
				'author'             => $this->_author,
				'modified'           => $this->_modified,
			  );
			  $data = $this->_safedataObj->rteSafe($data);
			  //echo '<pre>';print_r($data);exit;
			  $this->_connection->beginTransaction();
			  $result = $this->getDefinedTable(Organization\FurnitureTable::class)->save($data);
			  if($result > 0):
				  $this->_connection->commit();
				  $this->flashMessenger()->addMessage("success^ successfully edited Furniture");
			  else:
				  $this->_connection->rollback();
				  $this->flashMessenger()->addMessage("error^ Failed to edit Furniture");
			  endif;
			  return $this->redirect()->toRoute('ef', array('action'=>'furniture'));
		  }		
		  $ViewModel = new ViewModel([
			'title'            => 'Edit Furniture',
			'page'             => $page,
			'furniture'        => $this->getDefinedTable(Organization\FurnitureTable::class)->get($furniture_id),
			'furnituretypes'   => $this->getDefinedTable(Organization\FurnituremasterTable::class)->getAll(),
			'locations'        => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'organizations'    => $this->getDefinedTable(Administration\DepartmentTable::class)->getAll(),
			'organizationObj'  =>$this->getDefinedTable(Administration\LocationTable::class),
		  ]);
		  $ViewModel->setTerminal(True);
		  return $ViewModel;
	  }
	
}

