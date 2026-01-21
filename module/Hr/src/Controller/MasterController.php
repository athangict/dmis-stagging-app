<?php
namespace Hr\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Hr\Model As Hr;

class MasterController extends AbstractActionController
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
	 *  Employee type index
	 */
	public function emptypeAction()
	{
		$this->init();		
		return new ViewModel(array(
			'title' => 'Eployee Category',
			'emptype' => $this->getDefinedTable(Hr\EmployeeTypeTable::class)->getAll(),
		));
		
	} 
	
	/**
	 * add emptype action
	 */
	public function addemptypeAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(	
					'emp_type' => $form['emp_type'],
					'code' => $form['code'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\EmployeeTypeTable::class)->save($data);	
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New Employee Category successfully added");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new Employee Category");
			endif;
			return $this->redirect()->toRoute('master',array('action' => 'emptype'));			 
		}
		$ViewModel = new ViewModel(['title' => 'Add Employee Category',]);		 
		$ViewModel->setTerminal(True);
		return $ViewModel;	
	}
	
	/**
	 * edit emptype Action
	 **/
	public function editemptypeAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest()->getPost();
			$data=array(
					'id' => $this->_id,
					'emp_type' => $form['emp_type'],
					'code' => $form['code'],
					'author' =>$this->_author,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\EmployeeTypeTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Employee Category successfully updated");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to update Employee Category");
			endif;
			return $this->redirect()->toRoute('master',array('action' => 'emptype'));
		}	
		$ViewModel = new ViewModel(array(
			'title' => 'Edit Employee Category',
			'apptype' => $this->getDefinedTable(Hr\EmployeeTypeTable::class)->get($this->_id),	
		));		 
		$ViewModel->setTerminal(True);
		return $ViewModel;	
	}
	
	/**
	 *  Appointment type action
	 */
	public function apptypeAction()
	{
		$this->init();
		
		return new ViewModel(array(
			'title' => 'Appointment Types',
			'apptype' => $this->getDefinedTable(Hr\AppointmentTypeTable::class)->getAll(),
		));
		
	} 
	
	/**
	 * add apptype action
	 */
	public function addapptypeAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(	
					'type_of_appointment' => $form['apptype'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\AppointmentTypeTable::class)->save($data);
	
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New Type of Application successfully added");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new Type of Appointment");
			endif;
			return $this->redirect()->toRoute('master',array('action' => 'apptype'));			 
		}
		$ViewModel = new ViewModel(['title'=> 'Add Subject Teacher']);		 
		$ViewModel->setTerminal(True);
		return $ViewModel;	
	}
	
	/**
	 * edit apptype Action
	 **/
	public function editapptypeAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest()->getPost();
			$data=array(
					'id' => $this->_id,
					'type_of_appointment' => $form['apptype'],
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\AppointmentTypeTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Appointment Type successfully updated");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to edit Appointment Type");
			endif;
			return $this->redirect()->toRoute('master',array('action' => 'apptype'));
		}	
		$ViewModel = new ViewModel(array(
			'title'=> 'Edit Appointment Type',
			'apptype' => $this->getDefinedTable(Hr\AppointmentTypeTable::class)->get($this->_id),	
		));		 
		$ViewModel->setTerminal(True);
		return $ViewModel;	
	}
		

	/**
	 *  postlevel Action
	 **/
	public function postlevelAction()
	{
		$this->init();
		
		return new ViewModel(array(
			'title' => 'Post Level',
			'postlevel' => $this->getDefinedTable(Hr\PositionlevelTable::class)->getAll(),
		));
		
	} 
	
	/**
	 * add postlevel action
	 */
	public function addpostlevelAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest();
			$data = array(	
					'position_level' => $form->getPost('post_level'),				
					'code' => $form->getPost('code'),
					'min_pay' => $form->getPost('min_pay'),
					'increment' => $form->getPost('increment'),
					'max_pay' => $form->getPost('max_pay'),
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\PositionlevelTable::class)->save($data);
	
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New Post Level successfully added");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new Post Level");
			endif;
			return $this->redirect()->toRoute('master',array('action' => 'postlevel'));			 
		}
		$ViewModel = new ViewModel(['title'=>'Postion Level']);		 
		$ViewModel->setTerminal(True);
		return $ViewModel;	
	}
	
	/**
	 * edit postlevel Action
	 **/
	public function editpostlevelAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest();
			$data=array(
					'id' => $this->_id,
					'position_level' => $form->getPost('post_level'),					
					'code' => $form->getPost('code'),
					'min_pay' => $form->getPost('min_pay'),
					'increment' => $form->getPost('increment'),
					'max_pay' => $form->getPost('max_pay'),
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\PositionlevelTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Position Level successfully updated");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to edit Post Level");
			endif;
			return $this->redirect()->toRoute('master',array('action' => 'postlevel'));
		}	
		$ViewModel = new ViewModel(array(
			'title'=>'Postion Level',
			'postlevel' => $this->getDefinedTable(Hr\PositionlevelTable::class)->get($this->_id),	
		));		 
		$ViewModel->setTerminal(True);
		return $ViewModel;	
	}
	
     /**
      * post title Action
      **/
     public function posttitleAction()
	{
		$this->init();
		
		return new ViewModel(array(
			'title' => 'Postion Titles',
			'posttitle' => $this->getDefinedTable(Hr\PositiontitleTable::class)->getAll(),
			'post_levelObj' => $this->getDefinedTable(Hr\PositionlevelTable::class),
		));
		
	}
	/**
	 * add post title Action
	 **/ 
	 public function addposttitleAction()
    {
     $this->init();
    
        if($this->getRequest()->isPost()){
            $form = $this->getRequest();
            $data = array(  
                    'position_title' => $form->getPost('post'),
					'position_level' => $form->getPost('level'),
                    'author' =>$this->_author,
                    'created' =>$this->_created,
                    'modified' =>$this->_modified,
            );
            $data = $this->_safedataObj->rteSafe($data);
            $result = $this->getDefinedTable(Hr\PositiontitleTable::class)->save($data);
    
            if($result > 0):
                $this->flashMessenger()->addMessage("success^ Position title successfully added");
            else:
                $this->flashMessenger()->addMessage("error^ Failed to add position title");
            endif;
            return $this->redirect()->toRoute('master',array('action' => 'posttitle'));             
        }
        $ViewModel = new ViewModel(array(
			'title'=>'Add Postion Title',
				'position_levels' => $this->getDefinedTable(Hr\PositionlevelTable::class)->getAll(),
		));        
        $ViewModel->setTerminal(True);
        return $ViewModel;  
    }
    /**
     * edit post title Action
     **/	
    public function editposttitleAction()
    {
    	$this->init();
    	if($this->getRequest()->isPost())
    	{
    		$form=$this->getRequest();
    		$data=array(
    				'id' => $this->_id,
    				'position_title' => $form->getPost('post'),
					'position_level' => $form->getPost('level'),
    				'modified' =>$this->_modified,
    		);
    		$data = $this->_safedataObj->rteSafe($data);
    		$result = $this->getDefinedTable(Hr\PositiontitleTable::class)->save($data);
    		if($result > 0):
    		$this->flashMessenger()->addMessage("success^ Position title successfully updated");
    		else:
    		$this->flashMessenger()->addMessage("error^ Failed to edit Position title");
    		endif;
    		return $this->redirect()->toRoute('master', array('action'=>'posttitle'));
    	}
    
    	$ViewModel = new ViewModel(array(
				'title'=>'Edit Postion Title',
    			'post' => $this->getDefinedTable(Hr\PositiontitleTable::class)->get($this->_id),
				'position_levels' => $this->getDefinedTable(Hr\PositionlevelTable::class)->getAll(),
    	));
    	 
    	$ViewModel->setTerminal(True);
    	return $ViewModel;
    }
    
    /**
	 *  leave type action
	 */
	public function leavetypeAction()
	{
		$this->init();
		
		return new ViewModel(array(
			'title' => 'Leave Types',
			'leavetype' => $this->getDefinedTable(Hr\LeaveTypeTable::class)->getAll(),
		));		
	} 	
	
	
	/**
	 * addleavetype action
	 */
	public function addleavetypeAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest();
			$data = array(	
					'type' => $form->getPost('leavetype'),
					'total_days'=>$form->getPost('total_days'),
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\LeaveTypeTable::class)->save($data);
	
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New Type of Leave successfully added");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new Type of Leave");
			endif;
			return $this->redirect()->toRoute('master',array('action' => 'leavetype'));			 
		}
		$ViewModel = new ViewModel();		 
		$ViewModel->setTerminal(True);
		return $ViewModel;	
	}
	
	/**
	 * edit leave type Action
	 **/
	public function editleavetypeAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest();
			$data=array(
					'id' => $this->_id,
					'type' => $form->getPost('leavetype'),
					'total_days' => $form->getPost('total_days'),
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\LeaveTypeTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Leave Type successfully updated");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to edit Leave Type");
			endif;
			return $this->redirect()->toRoute('master',array('action' => 'leavetype'));
		}	
		$ViewModel = new ViewModel(array(
			'leavetype' => $this->getDefinedTable(Hr\LeaveTypeTable::class)->get($this->_id),	
		));		 
		$ViewModel->setTerminal(True);
		return $ViewModel;	
	}
	/**
	 *  Payhead action
	 */
	public function payheadAction()
	{
		$this->init();
		return new ViewModel(array(
				'title' => 'Payhead',
				'rowset' => $this->getDefinedTable(Hr\PayheadTable::class)->getAll(),
				'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
		));
	
	}	
	
	/**
	 * addpayhead action
	 */
	public function addpayheadAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'pay_head' => $form['pay_head'],
					'payhead_type' => $form['payhead_type'],
					'code' => $form['code'],
					'type' => $form['type'],
					'dlwp' => $form['dlwp'],
					'roundup' => $form['roundup'],
					'against' => ($form['against']== Null)?0:$form['against'],
					'percentage' => ($form['percentage']== Null)?0:$form['percentage'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\PayheadTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New Pay head successfully added");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new Pay head");
			endif;
			return $this->redirect()->toRoute('master',array('action'=>'payhead'));
		}
		$ViewModel = new ViewModel(array(
				'title'	=> 'Add Payhead',
				'payheads' => $this->getDefinedTable("Hr\PayheadTable")->getAll(),
				'payheadtypes' => $this->getDefinedTable(Hr\PayheadtypeTable::class)->getAll(),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * editpayheadAction
	 **/
	public function editpayheadAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest()->getPost();
			$data=array(
					'id' => $this->_id,
					'pay_head' => $form['pay_head'],
					'payhead_type' => $form['payhead_type'],
					'code' => $form['code'],
					'type' => $form['type'],
					'dlwp' => $form['dlwp'],
					'roundup' => $form['roundup'],
					'against' => ($form['against']==NULL)?0:$form['against'],
					'percentage' => ($form['percentage']== Null)?0:$form['percentage'],
					'author' => $this->_author,
					'modified' => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			//echo "<pre>";print_r($data);exit;
			$result = $this->getDefinedTable(Hr\PayheadTable::class)->save($data);
			if($result > 0):
				if($form['change_paystructure']=='1'):
					foreach($this->getDefinedTable(Hr\PaystructureTable::class)->get(array('sd.pay_head'=>$this->_id)) as $row):
						$employee = $row['employee'];
						if($form['against'] == '-1'):
							$base_amount = $this->getDefinedTable(Hr\TempPayrollTable::class)->getColumn(array('employee'=>$employee),'gross');
						elseif($form['against'] == '-2'):
							$Gross_amount = $this->getDefinedTable(Hr\TempPayrollTable::class)->getColumn(array('employee'=>$employee),'gross');
							$PFDed = $this->getDefinedTable(Hr\PaystructureTable::class)->getColumn(array('employee'=>$employee, 'pay_head'=>7),'amount');
							$GISDed = $this->getDefinedTable(Hr\PaystructureTable::class)->getColumn(array('employee'=>$employee, 'pay_head'=>6),'amount');
							$base_amount = $Gross_amount - $PFDed - $GISDed;
						else:
							$base_amount = $this->getDefinedTable(Hr\PaystructureTable::class)->getColumn(array('employee'=>$employee, 'pay_head'=>$form['against']),'amount');
						endif;
						if($form['type'] == 2 ):				
							$amount = ($base_amount*$form['percentage'])/100;
							if($form['roundup']==1):
								$amount =round($amount);
							endif;
							$ps_data = array(
								'id' => $row['id'],
								'percent' => ($form['percentage']== Null)?0:$form['percentage'],
								'dlwp' => $form['dlwp'],
								'amount' => $amount,
								'author' =>$this->_author,
								'modified' =>$this->_modified,
							);
							$ps_data = $this->_safedataObj->rteSafe($ps_data);
							$result = $this->getDefinedTable(Hr\PaystructureTable::class)->save($ps_data);
						elseif($form['type'] == 3):
							$rate=0;  $base=0;  $value=0;  $min=0;
							foreach($this->getDefinedTable(Hr\PaySlabTable::class)->get(array('pay_head' => $this->_id)) as $payslab):
								if($base_amount>=$payslab['from_range'] && $base_amount<=$payslab['to_range']):
									break;
								endif;
							endforeach;
							if($payslab['formula'] == 1):
								$rate = $payslab['rate'];
								$base = $payslab['base'];
								$min = $payslab['from_range'];
								if($base_amount > 158701):
									$amount = ((($base_amount - 83338)/100)*$rate)+$base;
								else:
									$amount = (intval(($base_amount - $min)/100)*$rate)+$base;
								endif;
							else:
								$amount=$payslab['value'];
							endif;
							if($form['roundup']==1):
								$amount =round($amount);
							endif;
							$ps_data = array(
								'id' => $row['id'],
								'percent' => ($form['percentage']== Null)?0:$form['percentage'],
								'dlwp' => $form['dlwp'],
								'amount' => $amount,
								'author' =>$this->_author,
								'modified' =>$this->_modified,
							);
							$ps_data = $this->_safedataObj->rteSafe($ps_data);
							$result = $this->getDefinedTable(Hr\PaystructureTable::class)->save($ps_data);
						endif;
					endforeach;
					foreach($this->getDefinedTable(Hr\PaystructureTable::class)->get(array('sd.pay_head'=>$this->_id)) as $row):
						$this->calculatePayheadAmount($row);
					endforeach;
				endif;
				$this->flashMessenger()->addMessage("success^ Pay head successfully updated");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to update Pay head");
			endif;
			return $this->redirect()->toRoute('master',array('action'=>'payhead'));
		}
		
		$ViewModel = new ViewModel(array(
				'title'	=> 'Edit Payhead',
				'payheadtypes' => $this->getDefinedTable(Hr\PayheadtypeTable::class)->getAll(),
				'payhead' => $this->getDefinedTable(Hr\PayheadTable::class)->get($this->_id),
				'payheads' => $this->getDefinedTable(Hr\PayheadTable::class)->getAll(),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 *  Payhead action
	 */
	public function payheadtypeAction()
	{
		$this->init();
	
		return new ViewModel(array(
				'title' => 'Payhead Type',				
				'payheadtypes' => $this->getDefinedTable(Hr\PayheadtypeTable::class)->getAll(),
		));
	
	}	
	
	/**
	 * addpayhead action
	 */
	public function addpayheadtypeAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'head_type' => $form['payhead_type'],
					'code' => $form['code'],
					'deduction' => $form['deduction'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Hr\PayheadtypeTable::class)->save($data);
			if($result > 0):
				$subheaddata = array(
					'head' => $form['head'],
					'type' => 5,
					'ref_id' => $result,
					'code' => $form['code'],
					'name' => $form['payhead_type'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
				);
				$subheaddata = $this->_safedataObj->rteSafe($subheaddata);
				$result1 = $this->getDefinedTable(Accounts\SubheadTable::class)->save($subheaddata);
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ New Pay head type successfully added");
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to add new Pay head type");
				endif;
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to add new Pay head type");
			endif;
			return $this->redirect()->toRoute('master',array('action'=>'payheadtype'));
		}
		$ViewModel = new ViewModel(array(
				'title'	=> 'Add Payhead Type',				
				'headtypes' => $this->getDefinedTable(Accounts\HeadtypeTable::class)->getAll(),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * editpayheadAction
	 **/
	public function editpayheadtypeAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest()->getPost();
			$old_deduction = $this->getDefinedTable(Hr\PayheadtypeTable::class)->getColumn($this->_id,'deduction');
			$data=array(
					'id' => $this->_id,
					'head_type' => $form['payhead_type'],
					'code' => $form['code'],
					'deduction' => $form['deduction'],
					'author' => $this->_author,
					'modified' => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Hr\PayheadtypeTable::class)->save($data);
			if($result > 0):
				if($form['change_paystructure']=='1' && $old_deduction!=$form['deduction']):
					foreach($this->getDefinedTable(Hr\TempPayrollTable::class)->getAll() as $temp_payroll):			
						$employee = $temp_payroll['employee'];
						$total_earning = 0;		
						$total_deduction = 0;
						foreach($this->getDefinedTable(Hr\PaystructureTable::class)->get(array('sd.employee' => $employee, 'pht.deduction'=>'1')) as $paydetails):
							if($paydetails['dlwp']==1):
								$amount = $paydetails['amount'] - ($paydetails['amount']/$temp_payroll['working_days']) * $temp_payroll['leave_without_pay'];
							else:
								$amount = $paydetails['amount'];
							endif;
							if($paydetails['roundup']==1):
								$amount =round($amount);
							endif;
							$total_deduction = $total_deduction + $amount;
						endforeach;	
						foreach($this->getDefinedTable(Hr\PaystructureTable::class)->get(array('sd.employee' => $employee, 'pht.deduction'=>'0')) as $paydetails):
							if($paydetails['dlwp']==1):
								$amount = $paydetails['amount'] - ($paydetails['amount']/$temp_payroll['working_days']) * $temp_payroll['leave_without_pay'];
							else:
								$amount = $paydetails['amount'];
							endif;
							if($paydetails['roundup']==1):
								$amount =round($amount);
							endif;
							$total_earning = $total_earning + $amount;
						endforeach;				
						$leave_encashment = $temp_payroll['leave_encashment'];
						$bonus = $temp_payroll['bonus'];
						$net_pay = $total_earning + $leave_encashment + $bonus - $total_deduction;
						$tp_data = array(
								'id'	=> $temp_payroll['id'],
								'gross' => $total_earning,
								'total_deduction' => $total_deduction,
								'net_pay' => $net_pay,
								'author' =>$this->_author,
								'modified' =>$this->_modified,
						);			
						$tp_data = $this->_safedataObj->rteSafe($tp_data);
						$tp_result = $this->getDefinedTable(Hr\TempPayrollTable::class)->save($tp_data);
					endforeach;
				endif;
				$subheaddata = array(
						'id'   => $form['subhead_id'],
						'head' => $form['head'],
						'type' => 5,
						'ref_id' => $result,
						'code' => $form['code'],
						'name' => $form['payhead_type'],
						'author' =>$this->_author,
						'modified' =>$this->_modified,
				);
				$subheaddata = $this->_safedataObj->rteSafe($subheaddata);
				$result1 = $this->getDefinedTable(Accounts\SubheadTable::class)->save($subheaddata);
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ Pay head type successfully updated");
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to update Pay head type");
				endif;
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to update Pay head type");
			endif;
			return $this->redirect()->toRoute('master',array('action'=>'payheadtype'));
		}
		$ViewModel = new ViewModel(array(
				'title'	=> 'Edit Payhead',
				'payheadtype' => $this->getDefinedTable(Hr\PayheadtypeTable::class)->get($this->_id),
				'headtypes' => $this->getDefinedTable(Accounts\HeadtypeTable::class)->getAll(),
				'subheads' => $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('ref_id' => $this->_id, 'sh.type' => '5')),
				'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 *  Payhead action
	 */
	public function paygroupAction()
	{
		$this->init();
	
		return new ViewModel(array(
				'title' => 'Pay Group',				
				'paygroups' => $this->getDefinedTable(Hr\PaygroupTable::class)->getAll(),
		));
	
	}	
	
	/**
	 * addpayhead action
	 */
	public function addpaygroupAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'group' => $form['group'],
					'pay_head' => $form['pay_head'],
					'value' => $form['value'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Hr\PaygroupTable::class)->save($data);
			if($result > 0):				
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ New Pay group successfully added");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to add new Pay group");
			endif;
			return $this->redirect()->toRoute('master',array('action'=>'paygroup'));
		}
		$ViewModel = new ViewModel(array(
				'title'	=> 'Add Pay Group',				
				'payheads' => $this->getDefinedTable(Hr\PayheadTable::class)->getAll(),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * editpaygroupAction
	 **/
	public function editpaygroupAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest()->getPost();
			$data=array(
					'id' => $this->_id,
					'group' => $form['group'],
					'pay_head' => $form['pay_head'],
					'value' => $form['value'],
					'author' =>$this->_author,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Hr\PaygroupTable::class)->save($data);
			if($result > 0):
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Pay group successfully updated");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to update Pay group");
			endif;
			return $this->redirect()->toRoute('master',array('action'=>'paygroup'));
		}
		$ViewModel = new ViewModel(array(
				'title'	=> 'Edit Payhead',
				'paygroup' => $this->getDefinedTable(Hr\PaygroupTable::class)->get($this->_id),
				'payheads' => $this->getDefinedTable(Hr\PayheadTable::class)->getAll(),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 *  Payslab action
	 */
	public function payslabAction()
	{
		$this->init();
	
		return new ViewModel(array(
				'title' => 'Pay Slab',
				'rowset' => $this->getDefinedTable(Hr\PaySlabTable::class)->getAll(),
				'payhead' => $this->getDefinedTable(Hr\PayheadTable::class)->getAll(),
				'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
		));
	
	}
	public function addpayslabAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest();
			$data = array(
					'pay_head' => $form->getPost('pay_head'),
					'formula' => $form->getPost('formula'),
					'from_range' => $form->getPost('from'),
					'to_range' => $form->getPost('to'),
					'rate' => $form->getPost('rate'),
					'base' => $form->getPost('base'),
					'value' => $form->getPost('value'),
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$result = $this->getDefinedTable(Hr\PaySlabTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ New Pay slab successfully added");
			else:
			$this->flashMessenger()->addMessage("error^ Failed to add new Pay slab");
			endif;
			return $this->redirect()->toRoute('master',array('action'=>'payslab'));
		}
		$ViewModel = new ViewModel(array(
				'payslab' => $this->getDefinedTable(Hr\PaySlabTable::class)->getAll(),
				'payhead' => $this->getDefinedTable(Hr\PayheadTable::class)->getAll(),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	public function editpayslabAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest();
			$data = array(
					'id'=> $this->_id,
					'pay_head' => $form->getPost('pay_head'),
					'formula' => $form->getPost('formula'),
					'from_range' => $form->getPost('from'),
					'to_range' => $form->getPost('to'),
					'rate' => $form->getPost('rate'),
					'base' => $form->getPost('base'),
					'value' => $form->getPost('value'),
					'modified' =>$this->_modified,
			);
			$result = $this->getDefinedTable(Hr\PaySlabTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Pay slab successfully updated");
			else:
			$this->flashMessenger()->addMessage("error^ Failed to update Pay slab");
			endif;
			return $this->redirect()->toRoute('master',array('action'=>'payslab'));
		}
		$ViewModel = new ViewModel(array(
				'id' => $this->_id,
				'payslab' => $this->getDefinedTable(Hr\PaySlabTable::class)->get($this->_id),
				'payhead' => $this->getDefinedTable(Hr\PayheadTable::class)->getAll(),
				'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/*
	 * function to calculate payhead amount on change of payheads
	 */
	public function calculatePayheadAmount($paystructure){
		$payhead_id =$paystructure['pay_head_id'];	
		$employee = $paystructure['employee'];
		$payhead_type = $this->getDefinedTable(Hr\PayheadTable::class)->getColumn($payhead_id, 'payhead_type');
		$deduction = $this->getDefinedTable(Hr\PayheadtypeTable::class)->getColumn($payhead_type, 'deduction');
		if($deduction == 1):
			$affected_ps = $this->getDefinedTable(Hr\PaystructureTable::class)->get(array('sd.employee'=>$employee, 'ph.against'=> $payhead_id));
		else:
			$affected_ps = $this->getDefinedTable(Hr\PaystructureTable::class)->get(array('sd.employee'=>$employee, 'ph.against'=> array($payhead_id,'-1','-2')));
		endif;
		foreach($affected_ps as $aff_ps):
			if($aff_ps['against'] == '-1'):
				$base_amount = $this->getDefinedTable(Hr\TempPayrollTable::class)->getColumn(array('employee'=>$employee),'gross');
			elseif($form['against'] == '-2'):
				$Gross_amount = $this->getDefinedTable(Hr\TempPayrollTable::class)->getColumn(array('employee'=>$employee),'gross');
				$PFDed = $this->getDefinedTable(Hr\PaystructureTable::class)->getColumn(array('employee'=>$employee, 'pay_head'=>7),'amount');
				$GISDed = $this->getDefinedTable(Hr\PaystructureTable::class)->getColumn(array('employee'=>$employee, 'pay_head'=>6),'amount');
				$base_amount = $Gross_amount - $PFDed - $GISDed;
			else:
				$base_amount = $this->getDefinedTable(Hr\PaystructureTable::class)->getColumn(array('employee'=>$employee, 'pay_head'=>$aff_ps['against']),'amount');
			endif;
			if($aff_ps['type'] == 2 ):				
				$amount = ($base_amount*$aff_ps['percent'])/100;
				if($aff_ps['roundup'] == 1):
					$amount = round($amount);
				endif;
				$data = array(
					'id' => $aff_ps['id'],
					'amount' => $amount,
					'author' =>$this->_author,
					'modified' =>$this->_modified,
				);
				$data = $this->_safedataObj->rteSafe($data);
				$result = $this->getDefinedTable(Hr\PaystructureTable::class)->save($data);
			elseif($aff_ps['type'] == 3):
				$rate=0;  $base=0;  $value=0;  $min=0;
				foreach($this->getDefinedTable(Hr\PaySlabTable::class)->get(array('pay_head' => $aff_ps['pay_head_id'])) as $payslab):
					if($base_amount>=$payslab['from_range'] && $base_amount<=$payslab['to_range']):
						break;
					endif;
				endforeach;
				if($payslab['formula'] == 1):
					$rate = $payslab['rate'];
					$base = $payslab['base'];
					$min = $payslab['from_range'];
					if($base_amount > 158701):
						$amount = ((($base_amount - 83338)/100)*$rate)+$base;
					else:
						$amount = (intval(($base_amount - $min)/100)*$rate)+$base;
					endif;
				else:
					$amount=$payslab['value'];
				endif;
				if($aff_ps['roundup'] == 1):
					$amount = round($amount);
				endif;
				$data = array(
					'id' => $aff_ps['id'],
					'amount' => $amount,
					'author' =>$this->_author,
					'modified' =>$this->_modified,
				);
				$data = $this->_safedataObj->rteSafe($data);
				$result = $this->getDefinedTable(Hr\PaystructureTable::class)->save($data);
			endif;
		endforeach;
		
		//making changes to temp payroll
		foreach($this->getDefinedTable(Hr\TempPayrollTable::class)->get(array('pr.employee' => $employee)) as $temp_payroll):				
			$total_earning = 0;		
			$total_deduction = 0;
			foreach($this->getDefinedTable(Hr\PaystructureTable::class)->get(array('sd.employee' => $employee, 'pht.deduction'=>'1')) as $paydetails):
				if($paydetails['dlwp']==1):
					$amount = $paydetails['amount'] - ($paydetails['amount']/$temp_payroll['working_days']) * $temp_payroll['leave_without_pay'];
				else:
					$amount = $paydetails['amount'];
				endif;
				if($paydetails['roundup']==1):
					$amount =round($amount);
				endif;
				$total_deduction = $total_deduction + $amount;
			endforeach;	
			foreach($this->getDefinedTable(Hr\PaystructureTable::class)->get(array('sd.employee' => $employee, 'pht.deduction'=>'0')) as $paydetails):
				if($paydetails['dlwp']==1):
					$amount = $paydetails['amount'] - ($paydetails['amount']/$temp_payroll['working_days']) * $temp_payroll['leave_without_pay'];
				else:
					$amount = $paydetails['amount'];
				endif;
				if($paydetails['roundup']==1):
					$amount =round($amount);
				endif;
				$total_earning = $total_earning + $amount;
			endforeach;				
			$leave_encashment = $temp_payroll['leave_encashment'];
			$bonus = $temp_payroll['bonus'];
			$net_pay = $total_earning + $leave_encashment + $bonus - $total_deduction;
			$data1 = array(
					'id'	=> $temp_payroll['id'],
					'gross' => $total_earning,
					'total_deduction' => $total_deduction,
					'net_pay' => $net_pay,
					'author' =>$this->_author,
					'modified' =>$this->_modified,
			);			
			$data1 = $this->_safedataObj->rteSafe($data1);
			$result1 = $this->getDefinedTable(Hr\TempPayrollTable::class)->save($data1);
		endforeach;
		return $result1;
	}
	
	/**
	 *  increment type Action
	 **/
	public function incrementtypeAction()
	{
		$this->init();
		
		return new ViewModel(array(
			'title' => 'Increment Type',
			'incrementtypes' => $this->getDefinedTable(Hr\IncrementTypeTable::class)->getAll(),
		));
		
	} 
	
	/**
	 * add increment type Action
	 */
	public function addincrementtypeAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest();
			$data = array(	
					'increment_type' => $form->getPost('increment_type'),	
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\IncrementTypeTable::class)->save($data);
	
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New Increment Type successfully added");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new Increment Type");
			endif;
			return $this->redirect()->toRoute('master',array('action' => 'incrementtype'));			 
		}
		$ViewModel = new ViewModel();		 
		$ViewModel->setTerminal(True);
		return $ViewModel;	
	}
	
	/**
	 * edit increment type Action
	 **/
	public function editincrementtypeAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest();
			$data=array(
					'id' => $this->_id,
					'increment_type' => $form->getPost('increment_type'),	
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\IncrementTypeTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Increment Type successfully updated");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to edit Increment Type");
			endif;
			return $this->redirect()->toRoute('master',array('action' => 'incrementtype'));
		}	
		$ViewModel = new ViewModel(array(			
			'incrementtypes' => $this->getDefinedTable(Hr\IncrementTypeTable::class)->get($this->_id),	
		));		 
		$ViewModel->setTerminal(True);
		return $ViewModel;	
	}
}




