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
use Accounts\Model As Accounts;

class PayrollController extends AbstractActionController
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
	
	
	/** funtion to get employee list 
	 * base on authority
	 */
	public function getEmployee()
	{		
		$emp_id = $this->_user->employee;
		$location_str = $this->getDefinedTable(Acl\UsersTable::class)->getcolumn($this->_user->id,'admin_location');
		$locations = !empty($location_str) ? explode(',',$location_str) : array();
		if($this->_login_role==$this->_highest_role):
			$employeelist = $this->getDefinedTable(Hr\EmployeeTable::class)->getAll();
		else:
			$employeelist = $this->getDefinedTable(Hr\EmployeeTable::class)->getAll($emp_id, $locations);
		endif;
		return $employeelist;
	} 
	
	/**
	 *  Monthly pay index action
	 */
	public function indexAction()
	{
		$this->init();	
		$year = ($this->_id == 0)? date('Y'):$this->_id;	
		return new ViewModel(array(
				'title'  => 'Payroll',
				'payroll' => $this->getDefinedTable(Hr\PayrollTable::class)->getPayroll($year),
				'temppayroll' => $this->getDefinedTable(Hr\TempPayrollTable::class)->getPayroll($year),
				'minYear' => $this->getDefinedTable(Hr\PayrollTable::class)->getMin('year'),
				'maxYear' => $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year'),
				'year' => $year,
				'payrollObj' => $this->getDefinedTable(Hr\PayrollTable::class),
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
		));
	}
	/**
	 *  payroll action displays pay detail action of particular month
	 * 
	 */
	public function payrollAction()
	{
		$this->init();
		$id_parts = !empty($this->_id) ? explode('-', $this->_id) : array('0', '0');
		list($year, $month) = $id_parts;
		$month = ($month == 0)? date('m'):$month;
		$year = ($year == 0)? date('Y'):$year;
		if(!$this->getDefinedTable(Hr\PayrollTable::class)->isPresent(array('month'=>$month, 'year'=>$year))):
			$this->redirect()->toRoute('payroll',array('action'=>'definepayroll','id'=>$year.'-'.$month));
		endif;		
		return new ViewModel(array(
				'title'  => 'Payroll',
				'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
				'payroll' => $this->getDefinedTable(Hr\PayrollTable::class)->get(array('month'=>$month, 'year'=>$year)),
				'month' => $month,
				'year' => $year,
                'locationObj'=>$this->getDefinedTable(Administration\LocationTable::class),
                'deptObj' =>$this->getDefinedTable(Administration\DepartmentTable::class),
				'actObj' =>$this->getDefinedTable(Administration\ActivityTable::class),
				'bookingbutton' => (sizeof($this->getDefinedTable(Hr\SalarybookingTable::class)->get(array('month'=> $month,'year'=> $year,'salary_advance'=>'1')))> 0)? True:False,
				'advancebutton' => (sizeof($this->getDefinedTable(Hr\SalarybookingTable::class)->get(array('month'=> $month,'year'=> $year,'salary_advance'=>'2')))> 0)? True:False,
		));
	}
	/*
	 * generate /update (define) payroll for new month
	 * updation will be all done in tempayroll table
	 */
	public function definepayrollAction()
	{
		$this->init();
		$this->_id = isset($this->_id)?$this->_id:date('Y-m-d');
		$id_parts = !empty($this->_id) ? explode('-', $this->_id) : array('0', '0');
		list($year, $month) = $id_parts;	  
	
		if($year == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$year = ($max_month == 12)? $max_year+1 : $max_year;
		endif;
		if($month == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$month = ($max_month == 12)? 1 : $max_month+1;
		endif;
		if($this->getDefinedTable(Hr\PayrollTable::class)->isPresent(array('month'=>$month, 'year'=>$year))):
			$this->redirect()->toRoute('payroll',array('id'=>$year.'-'.$month));
		endif;
		$data=array(
			'month'=> $month,
			'year' => $year,
			'author'=> $this->_author,
			'created'=>$this->_created,
			'modified' => $this->_modified
		);
		//prepare temporary payroll
		$this->getDefinedTable(Hr\TempPayrollTable::class)->prepareTempPayroll($data);
		foreach($this->getDefinedTable(Hr\TempPayrollTable::class)->get(array('pr.status'=>'0')) as $temp_payroll):
			$employee = $temp_payroll['employee'];
			$total_earning = 0;		
			$total_deduction = 0;
			$total_actual_earning = 0;
			$total_actual_deduction = 0;
			foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get(array('sd.employee' => $employee, 'pht.deduction'=>'1')) as $paydetails):
				if($paydetails['dlwp']==1):
					$amount = $paydetails['amount'] - ($paydetails['amount']/$temp_payroll['working_days']) * $temp_payroll['leave_without_pay'];
				else:
					$amount = $paydetails['amount'];
				endif;
				if($paydetails['roundup']==1):
					$amount =round($amount);
				endif;
				$total_deduction = $total_deduction + $amount;
				$total_actual_deduction = $total_actual_deduction + $paydetails['amount'];
			endforeach;	
			foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get(array('sd.employee' => $employee, 'pht.deduction'=>'0')) as $paydetails):
				if($paydetails['dlwp']==1):
					$amount = $paydetails['amount'] - ($paydetails['amount']/$temp_payroll['working_days']) * $temp_payroll['leave_without_pay'];
				else:
					$amount = $paydetails['amount'];
				endif;
				if($paydetails['roundup']==1):
					$amount =round($amount);
				endif;
				$total_earning = $total_earning + $amount;
				$total_actual_earning = $total_actual_earning + $paydetails['amount'];
			endforeach;				
			$leave_encashment = $temp_payroll['leave_encashment'];
			$bonus = $temp_payroll['bonus'];
			$net_pay = $total_earning + $leave_encashment + $bonus - $total_deduction;
			$earning_dlwp = $total_actual_earning - $total_earning;
			$deduction_dlwp = $total_actual_deduction - $total_deduction;
			$data1 = array(
					'id'	=> $temp_payroll['id'],
					'gross' => $total_earning,
					'total_deduction' => $total_deduction,
					'net_pay' => $net_pay,
					'earning_dlwp' => $earning_dlwp,
					'deduction_dlwp' => $deduction_dlwp,
					'status' => '1', // initiated
					'author' =>$this->_author,
					'modified' =>$this->_modified,
			);			
			$data1 = $this->_safedataObj->rteSafe($data1);			
			
			$result1 = $this->getDefinedTable(Hr\TempPayrollTable::class)->save($data1);
		endforeach;
		
		return new ViewModel(array(
				'title' => 'Add Pay roll',
				'month' => $month,
				'year' => $year,
				'temppayroll' => $this->getDefinedTable(Hr\TempPayrollTable::class)->getAll(),
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
				'payStructureObj' => $this->getDefinedTable(Hr\PaystructureTable::Class),

		));
	}
	
	/**
	 * define pay payroll for a particular month
	 */
	public function definepayAction(){
		$this->init();
		$payheads = array();
		if(isset($this->_id) & $this->_id!=0):
			$payheads = !empty($this->_id) ? explode('-', $this->_id) : array();
		endif;
		if(sizeof($payheads)==0):
			$payheads = array('1'); //default selection
		endif;
		return new ViewModel(array(
				'title' => 'Define Pay Detail',
				'employees'=> $this->getDefinedTable(Hr\EmployeeTable::class)->get(array('e.status'=>'1')),
				'payheads'=> $payheads,
				'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::Class),
		));
	}
	
	/*
	 * Edit/update payroll -- basically temporary payroll
	 */
	public function editpayrollAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();	
			//print_r($form); exit; 
			$employee = $this->getDefinedTable(Hr\TempPayrollTable::class)->getColumn($this->_id,'employee');
			$total_earning = 0;
			$total_deduction = 0;
			$total_actual_earning = 0;
			$total_actual_deduction = 0;
			$e_dlwp = 0;
			$d_dlwp = 0;
			foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get(array('employee' => $employee)) as $paydetails):		
				if($paydetails['deduction'] == "1"){
					if($paydetails['dlwp']==1):
						$amount = $paydetails['amount'] - ($paydetails['amount']/$form['working_days']) * $form['leave_without_pay'];
					else:
						$amount = $paydetails['amount'];
					endif;
					$final_amt = $amount = $paydetails['amount'] - $amount;
					$final_amt = round($final_amt,2);
					$d_dlwp += $final_amt;
					if($paydetails['roundup']==1):
						$amount =round($amount);
					endif;
					$total_deduction = $total_deduction + $amount;
					$total_actual_deduction = $total_actual_deduction + $paydetails['amount'];
				}
				else
				{
					if($paydetails['dlwp']==1):
						$amount = $paydetails['amount'] - ($paydetails['amount']/$form['working_days']) * $form['leave_without_pay'];
					else:
						$amount = $paydetails['amount'];
					endif;
					$final_amt = $amount = $paydetails['amount'] - $amount;
					$final_amt = round($final_amt,2);
					$e_dlwp += $final_amt;
					if($paydetails['roundup']==1):
						$amount =round($amount);
					endif;
					$total_earning = $total_earning + $amount;
					$total_actual_earning = $total_actual_earning + $paydetails['amount'];
				}
			endforeach;				
			$leave_encashment = $form['leave_encashment'];
			$bonus = $form['bonus'];
			$net_pay = $total_actual_earning + $leave_encashment + $bonus - $total_actual_deduction - $e_dlwp - $d_dlwp;
			//$earning_dlwp = $total_actual_earning - $total_earning;
			//$deduction_dlwp = $total_actual_deduction - $total_deduction;
			$earning_dlwp = $e_dlwp;
			$deduction_dlwp = $d_dlwp;
			$data = array(
					'id'	=> $this->_id,
					'year' => $form['year'],
					'month' => $form['month'],
					'working_days' => $form['working_days'],
					'leave_without_pay' => $form['leave_without_pay'],
					'gross' => $total_actual_earning,
					'total_deduction' => $total_actual_deduction,
					'bonus' => $form['bonus'],
					'leave_encashment' => $leave_encashment,
					'net_pay' => $net_pay,
					'earning_dlwp' => $earning_dlwp,
					'deduction_dlwp' => $deduction_dlwp,
					'status' => '1', // initiated
					'author' =>$this->_author,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Hr\TempPayrollTable::class)->save($data);
			if($result > 0):
				if($form['cash'] == 1){
					$bank_account_no = '0';
				}else{
					$bank_account_no = $form['bank_account_no'];
				}
				$hr_data = array(
						'id' => $employee,
						'bank_account_no' => $bank_account_no,
				);
				$hr_result = $this->getDefinedTable(Hr\EmployeeTable::class)->save($hr_data);
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Payroll succesfully updated");
				//return $this->redirect()->toRoute('payroll', array('action'=>'payroll','id'=>$form['year'].'-'.$form['month']));
				return $this->redirect()->toRoute('payroll', array('action'=>'editpayroll', 'id'=>$this->_id));
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to update Payroll");
				return $this->redirect()->toRoute('payroll', array('action'=>'editpayroll', 'id'=>$this->_id));
			endif;
		}	
		return new ViewModel(array(
				'title' => 'Edit Pay roll',
				'payroll' => $this->getDefinedTable(Hr\TempPayrollTable::class)->get($this->_id),
		        'tempPrObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::Class),
				'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),

		));
	}
	
	/**
	 * define pay :edit paydetail
	 */
	public function editpaydetailAction(){
		$this->init();
		$id_parts = !empty($this->_id) ? explode('&', $this->_id) : array('0', '0', '0');
		list($employee, $payhead, $payheads) = $id_parts;
		if($this->getRequest()->isPost()):
			$form=$this->getRequest()->getPost();	
			$roundup = $this->getDefinedTable(Hr\PayheadTable::class)->getColumn($payhead, 'roundup');
			if($roundup == 1):
				$form['amount'] = round($form['amount']);
			endif;		
			if($form['id'] > 0):
				$data = array(
					'id' => $form['id'],
					'pay_head' => $payhead,
					'percent' => $form['percent'],
					'amount' => $form['amount'],
					'dlwp' => $form['dlwp'],
					'ref_no' => $form['ref_no'],
					'remarks' => $form['remarks'],
					'modified' =>$this->_modified,	
				);
			else:
				$data = array(
						'employee' => $employee,
						'pay_head' => $payhead,
						'percent' => $form['percent'],
						'amount' => $form['amount'],
						'dlwp' => $form['dlwp'],
						'ref_no' => $form['ref_no'],
						'remarks' => $form['remarks'],
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
				);
			endif;
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction();//****Transaction begins here ***//
			$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->save($data);
			if($result > 0):
				//changes in paystructure should affect other payheads and temporary payroll
				foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get($result) as $row);				
				$result1 = $this->calculatePayheadAmount($row);
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ Pay Detail successfully Updated");	
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to Update");
				endif;
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to Update");
			endif;
			return $this->redirect()->toRoute('payroll', array('action'=>'definepay','id'=>$payheads));		
		else:
			$ViewModel = new ViewModel(array(
					'title' => 'Define Payhead',
					'head_type' => $this->getDefinedTable(Hr\PayheadTable::class)->getColumn($payhead, 'type'),
					'paystructure' => $this->getDefinedTable(Hr\PaystructureTable::Class)->get(array('employee'=>$employee, 'sd.pay_head'=> $payhead)),
					'pay_head_id' => $payhead,
					'get_id' => $this->_id,
					'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::Class),
					'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
					'paygroupObj' => $this->getDefinedTable(Hr\PaygroupTable::class),
					'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class)
			));
			$ViewModel->setTerminal(True);
			return $ViewModel;
		endif;
	}
	
	/**
	 * define pay :delete paydetail
	 */
	public function deletepaydetailAction(){
		$this->init();
		$id_parts = !empty($this->_id) ? explode('-', $this->_id) : array('0', '0', '0');
		list($employee, $payhead, $payheads) = $id_parts;
		foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get(array('sd.employee'=>$employee, 'sd.pay_head'=>$payhead)) as $row);
		$this->_connection->beginTransaction(); //***Transaction begins here***//
		$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->remove($row['id']);
		if($result > 0):
			//changes in paystructure should affect other payheads
			$result1 = $this->calculatePayheadAmount($row);
			if($result1 > 0):
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Paydetail deleted successfully");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to delete Paydetail");
			endif;
			//end			
		else:
			$this->_connection->rollback(); // rollback transaction over failure
			$this->flashMessenger()->addMessage("error^ Failed to delete Paydetail");
		endif;
		$redirectUrl = $this->getRequest()->getHeader('Referer')->getUri();	
		return $this->redirect()->toUrl($redirectUrl);
	}
	
	/**
	 * define pay :delteAll/resetAll paydetail
	 */
	public function deleteallpaydetailAction(){
		$this->init();
		$id_parts = !empty($this->_id) ? explode('-', $this->_id) : array('0', '0');
		list($payhead, $payheads) = $id_parts;
		$this->_connection->beginTransaction(); //***Transaction begins here***//
		foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get(array('sd.pay_head'=>$payhead)) as $row):
			$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->remove($row['id']);
			if($result > 0):
				//changes in paystructure should affect other payheads
				$result1 = $this->calculatePayheadAmount($row);
				if($result1 <= 0):
					break;
				endif;
				//end			
			else:
				$result1 =0;
				break;
			endif;
		endforeach;
		if($result1 > 0):
			$this->_connection->commit(); // commit transaction on success
			$this->flashMessenger()->addMessage("success^ Paydetail deleted successfully");
		else:
			$this->_connection->rollback(); // rollback transaction over failure
			$this->flashMessenger()->addMessage("error^ Failed to delete Paydetail");
		endif;
		$redirectUrl = $this->getRequest()->getHeader('Referer')->getUri();	
		return $this->redirect()->toUrl($redirectUrl);
	}
	/*
	 * Add earning and deduction to paystructure
	 */
	public function addAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();	
			$roundup = $this->getDefinedTable(Hr\PayheadTable::class)->getColumn($form['pay_head'], 'roundup');
			if($roundup == 1):
				$form['amount'] = round($form['amount']);
			endif;					
			$data = array(
					'employee' => $this->_id,
					'pay_head' => $form['pay_head'],
					'percent' => $form['percent'],
					'amount' => $form['amount'],
					'dlwp' => $form['dlwp'],
					'ref_no' => $form['ref_no'],
					'remarks' => $form['remarks'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->save($data);			
			if($result > 0):
				//changes in paystructure should affect other payheads
				foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get($result) as $row);				
				$result1 = $this->calculatePayheadAmount($row);
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ New Pay head successfully added to Pay Structure");
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to add new pay head");
				endif;
				//end
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new pay head");
			endif;
			$redirectUrl = $this->getRequest()->getHeader('Referer')->getUri();	
			return $this->redirect()->toUrl($redirectUrl);
			//return $this->redirect()->toRoute('payroll', array('action'=>'paystructure', 'id' => $this->_id));
		}
		$viewModel = new ViewModel(array(
				'employee' => $this->_id,
				'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::Class),
		));

		$viewModel->setTerminal(True);
		return $viewModel;
	}

	/*
	 * Edit earning & dediction to paystructure
	* */
	public function editAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$roundup = $this->getDefinedTable(Hr\PayheadTable::class)->getColumn($form['pay_head'], 'roundup');
			if($roundup == 1):
				$form['amount'] = round($form['amount']);
			endif;
			$data = array(
					'id' => $this->_id,
					'pay_head' => $form['pay_head'],
					'percent' => $form['percent'],
					'amount' => $form['amount'],
					'dlwp' => $form['dlwp'],
					'ref_no' => $form['ref_no'],
					'remarks' => $form['remarks'],
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->save($data);
			if($result > 0):			
				//changes in paystructure should affect other payheads
				foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get($this->_id) as $row);				
				$result1 = $this->calculatePayheadAmount($row);
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ Pay Structure successfully Updated");
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to Update pay detail in Paystructure");
				endif;
				//end
			else:
				$this->flashMessenger()->addMessage("error^ Failed to Update pay detail in Paystructure");
			endif;
			$redirectUrl = $this->getRequest()->getHeader('Referer')->getUri();	
			return $this->redirect()->toUrl($redirectUrl);
			//return $this->redirect()->toRoute('payroll', array('action'=>'paystructure', 'id' => $employee_id));			
		}
		$viewModel = new ViewModel(array(
				'title' => 'Edit Earning/Deduction',
				'paystructure' => $this->getDefinedTable(Hr\PaystructureTable::Class)->get($this->_id),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::Class),
				'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
				'paygroupObj' => $this->getDefinedTable(Hr\PaygroupTable::class),
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class)
		));
		$viewModel->setTerminal(True);
		return $viewModel;
	}

	/**
	 * action to delete pay head from paystructure
	 */
	public function deleteAction()
	{
		$this->init();
		$employee = $this->getDefinedTable(Hr\PaystructureTable::Class)->getColumn($this->_id,'employee');
		foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get($this->_id) as $row);
		$this->_connection->beginTransaction(); //***Transaction begins here***//
		$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->remove($this->_id);
		if($result > 0):
			//changes in paystructure should affect other payheads
			$result1 = $this->calculatePayheadAmount($row);
			if($result1 > 0):
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Payhead deleted successfully");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to delete Payhead");
			endif;
			//end			
		else:
			$this->_connection->rollback(); // rollback transaction over failure
			$this->flashMessenger()->addMessage("error^ Failed to delete Payhead");
		endif;
		$redirectUrl = $this->getRequest()->getHeader('Referer')->getUri();	
		return $this->redirect()->toUrl($redirectUrl);
	}
	/*
	 * Action for add earnings and deductions to temp payroll
	 */
	public function addprAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();	
			$roundup = $this->getDefinedTable(Hr\PayheadTable::class)->getColumn($form['pay_head'], 'roundup');
			if($roundup == 1):
				$form['amount'] = round($form['amount']);
			endif;			
			$data = array(
					'employee' => $this->_id,
					'pay_head' => $form['pay_head'],
					'percent' => $form['percent'],
					'amount' => $form['amount'],
					'dlwp' => $form['dlwp'],
					'ref_no' => $form['ref_no'],
					'remarks' => $form['remarks'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->save($data);				
			if($result > 0):
				//changes in paystructure should affect other payheads
				foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get($result) as $row);				
				$result1 = $this->calculatePayheadAmount($row);
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ New Pay head successfully added");	
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to add new pay head");
				endif;
				//end
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to add new pay head");
			endif;
			$redirectUrl = $this->getRequest()->getHeader('Referer')->getUri();	
			return $this->redirect()->toUrl($redirectUrl);
			//return $this->redirect()->toRoute('payroll', array('action'=>'paystructure', 'id' => $this->_id));
		}
		$viewModel = new ViewModel(array(
				'employee' => $this->_id,
				'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::Class),
		));

		$viewModel->setTerminal(True);
		return $viewModel;
	}

	/*
	 * Edit earning & dediction to temp payroll
	* */
	public function editprAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$roundup = $this->getDefinedTable(Hr\PayheadTable::class)->getColumn($form['pay_head'], 'roundup');
			if($roundup == 1):
				$form['amount'] = round($form['amount']);
			endif;
			$data = array(
					'id' => $this->_id,
					'pay_head' => $form['pay_head'],
					'percent' => $form['percent'],
					'amount' => $form['amount'],
					'dlwp' => $form['dlwp'],
					'ref_no' => $form['ref_no'],
					'remarks' => $form['remarks'],
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->save($data);
			if($result > 0):
				//changes in paystructure should affect other payheads and temporary payroll
				foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get($this->_id) as $row);				
				$result1 = $this->calculatePayheadAmount($row);	
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ Pay detail successfully Updated");
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to Update pay detail");
				endif;
				//end
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to Update pay detail");
			endif;	
			$redirectUrl = $this->getRequest()->getHeader('Referer')->getUri();	
			return $this->redirect()->toUrl($redirectUrl);
			//return $this->redirect()->toRoute('payroll', array('action'=>'paystructure', 'id' => $employee));			
		}
		$viewModel = new ViewModel(array(
				'title' => 'Edit Earning/Deduction',
				'paystructure' => $this->getDefinedTable(Hr\PaystructureTable::Class)->get($this->_id),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::Class),
				'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
				'paygroupObj' => $this->getDefinedTable(Hr\PaygroupTable::class),
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class)
		));
		$viewModel->setTerminal(True);
		return $viewModel;
	}

	/**
	 * action to delete
	 */
	public function deleteprAction()
	{
		$this->init();
		$employee = $this->getDefinedTable(Hr\PaystructureTable::Class)->getColumn($this->_id,'employee');
		foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get($this->_id) as $row);
		$this->_connection->beginTransaction(); //***Transaction begins here***//
		$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->remove($this->_id);
		if($result > 0):
			//changes in paystructure should affect other payheads
			$result1 = $this->calculatePayheadAmount($row);
			if($result1 > 0):
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Payhead deleted successfully");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to delete Payhead");
			endif;
			//end			
		else:
			$this->_connection->rollback(); // rollback transaction over failure
			$this->flashMessenger()->addMessage("error^ Failed to delete Payhead");
		endif;
		$redirectUrl = $this->getRequest()->getHeader('Referer')->getUri();	
		return $this->redirect()->toUrl($redirectUrl);
	}
	/*
	 * Ajax response action to get payslab type
	 * actual amount(value), percent, slab
	* */

	public function getslabtypeAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$request = $this->getRequest()->getPost();			
			$ViewModel = new ViewModel(array(
					'employee' => $request['employee'],
					'pay_head' => $request['pay_head'],
					'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
					'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::Class),
					'tempPayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
					'payslabTable' => $this->getDefinedTable(Hr\PaySlabTable::class),
					'paygroupObj' => $this->getDefinedTable(Hr\PaygroupTable::class)
			));
			$ViewModel->setTerminal(True);
			return $ViewModel;
		endif;
		exit;
	}
	
	/*
	 * Submit payroll to the accounts section
	* */
	public function submitpayrollAction()
	{
		$this->init();
		$this->_connection->beginTransaction(); //***Transaction begins here***//
		foreach($this->getDefinedTable(Hr\TempPayrollTable::class)->getAll() as $temp_payroll):
			$payroll_data = array(
				'employee' => $temp_payroll['employee'],
				'emp_his' => $temp_payroll['emp_his'],
				'year' => $temp_payroll['year'],
				'month' => $temp_payroll['month'],
				'working_days' => $temp_payroll['working_days'],
				'leave_without_pay' => $temp_payroll['leave_without_pay'],
				//'activity' => $temp_payroll['activity'],
				'gross' => $temp_payroll['gross'],
				'total_deduction' => $temp_payroll['total_deduction'],
				'bonus' => $temp_payroll['bonus'],
				'leave_encashment' => $temp_payroll['leave_encashment'],
				'net_pay' => $temp_payroll['net_pay'],
				'earning_dlwp' => $temp_payroll['earning_dlwp'],
				'deduction_dlwp' => $temp_payroll['deduction_dlwp'],
				'status' => '1', // initiated
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
			);
			$payroll_data = $this->_safedataObj->rteSafe($payroll_data);
			$result = $this->getDefinedTable(Hr\PayrollTable::class)->save($payroll_data);
			if($result > 0):
				foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get(array('employee'=> $temp_payroll['employee'])) as $pay_detail):
					$default_amt = $pay_detail['amount'];
					if($pay_detail['dlwp'] == 1):
						$working_days = $temp_payroll['working_days'];
						$leave_without_pay = $temp_payroll['leave_without_pay'];
						$amt = ($default_amt / $working_days)*$leave_without_pay;
						$final_amt = $default_amt - $amt;
					else:
						$final_amt = $default_amt;
					endif;
					if($pay_detail['roundup'] == 1):
						$final_amt = round($final_amt);
					endif;
					$paydetail_data = array(
						'pay_roll' => $result,
						'pay_head' => $pay_detail['pay_head_id'],
						'amount' => $final_amt,
						'actual_amount' => $default_amt,
						'ref_no' => $pay_detail['ref_no'],
						'remarks' => $pay_detail['remarks'],
						'author' => $this->_author,
						'created' => $this->_created,
						'modified' =>$this->_modified,
					);
					$paydetail_data = $this->_safedataObj->rteSafe($paydetail_data);
					$result1 = $this->getDefinedTable(Hr\PaydetailTable::class)->save($paydetail_data);
					if($result1 <= 0):
						break;
					endif;
				endforeach;
				if($result1 <= 0):
					break;
				endif;
			else:
				break;
			endif;
		endforeach; 
		if($result1 > 0 && $result > 0):
			$this->_connection->commit(); // Success transaction
			$this->flashMessenger()->addMessage("success^ Payroll successfully submitted");	
		else:
			$this->_connection->rollback(); // rollback transaction over failure
			$this->flashMessenger()->addMessage("error^ Failed while submitting payroll, Try again after some time");	
			return $this->redirect()->toRoute('payroll', array('action'=>'definepayroll'));
		endif;
		return $this->redirect()->toRoute('payroll', array('action'=>'index'));
	}
	
	/**
	 * payslip Action
	 */
	public function payslipAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$request = $this->getRequest()->getPost();
			$year = $request['year'];
			$month = $request['month'];
			$region = $request['region'];
			$location = $request['location'];
			$employee = $request['employee'];
		else:
			$employee = '';//set default employee to -1 meaning all employee
			$location = '';//set default location to -1 meaning all employee
			$region = '';//set default region to -1 meaning all employee			
			$month =  date('m');
			$year = date('Y');
			if($this->_id > 0):
				$id_parts = !empty($this->_id) ? explode('-', $this->_id) : array('', '', '');
				list($employee, $year, $month) = $id_parts;
				$location = $this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($employee,'location');
				$region = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($location,'region');
			endif;
		endif;
		$location_str = $this->getDefinedTable(Administration\UsersTable::class)->getcolumn($this->_user->id,'admin_location');
		$locations = !empty($location_str) ? explode(',',$location_str) : array();
		return new ViewModel(array(
				'title' => 'Salary Slip',
				'year' => $year,
				'month' => $month,
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'region_id' => $region,
				'location_id' => $location,
				'employee_id' => $employee,
				'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
				'emphistoryObj' => $this->getDefinedTable(Hr\EmpHistoryTable::class),
				'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
				'payrollObj' => $this->getDefinedTable(Hr\PayrollTable::class),
				'paydetailObj' => $this->getDefinedTable(Hr\PaydetailTable::class),
		));
	}
	
	/**
	 * Monthly salary booking in transaction
	 */
	public function booksalaryAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			// booking to transaction
			if(isset($form['voucher_date']) && isset($form['voucher_amount'])):
				//generate voucher no
				$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
				$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn($form['voucher_type'],'prefix');
				$date = date('ym',strtotime($form['voucher_date']));
				$tmp_VCNo = $loc.$prefix.$date;
				//$serial = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($date) + 1;
				
				$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
				
				$pltp_no_list = array();
				foreach($results as $result):
					array_push($pltp_no_list, substr($result['voucher_no'], 8));
				endforeach;
				$next_serial = max($pltp_no_list) + 1;
				switch(strlen($next_serial)){
					case 1: $next_dc_serial = "000".$next_serial; break;
					case 2: $next_dc_serial = "00".$next_serial;  break;
					case 3: $next_dc_serial = "0".$next_serial;   break;
					default: $next_dc_serial = $next_serial;       break;
				}	
				$voucher_no = $tmp_VCNo.$next_dc_serial;
					
				//$voucher_no = $loc.$prefix.$date.$serial;
				$data1 = array(
						'voucher_date' => $form['voucher_date'],
						'voucher_type' => $form['voucher_type'],
						'doc_id' => $form['doc_id'],
						'doc_type' => $form['doc_type'],
						'voucher_no' => $voucher_no,
						'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
						'remark' => $form['remark'],
						'status' => '1',
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
				);
				$data1 = $this->_safedataObj->rteSafe($data1);
				$this->_connection->beginTransaction(); //***Transaction begins here***//
				$result = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
				if($result > 0):
					//insert into salarybooking table
					$sb_data = array(
							'transaction' => $result,
							'year' => $form['year'],
							'month' => $form['month'],
							'salary_advance' => '1',
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
					);
					$result1 = $this->getDefinedTable(Hr\SalarybookingTable::class)->save($sb_data);
					if($result1 > 0):
						//insert into transactiondetail table from payroll table
						$data = array(
							'year' => $form['year'],
							'month' => $form['month'],
						);			
						$locations = $this->getDefinedTable(Hr\PayrollTable::class)->salaryBookingLocation($data);
						
						foreach($locations as $loc_row):
							$activities = $this->getDefinedTable(Hr\PayrollTable::class)->salaryBookingActivity($loc_row['location_id']);
							foreach($activities as $act_row):
								$sh_data = array(
									'year' => $data['year'],
									'month' => $data['month'],
									'location' => $loc_row['location_id'],
									'activity' => $act_row['activity_id'],
									'deduction' => '0',
								);
								$subheads = $this->getDefinedTable(Hr\PayrollTable::class)->salaryBookingSubhead($sh_data);
								foreach($subheads as $subhead_row):
									$filter = array(
										'year' => $sh_data['year'],
										'month' => $sh_data['month'],
										'location' => $sh_data['location'],
										'activity' => $sh_data['activity'],
										'subhead' => $subhead_row['ref_id'],
										'region' => '-1',
										'department' => '-1',													
									);
									$amt = $this->getDefinedTable(Hr\PaydetailTable::class)->getAmtforSummary($filter);
									
									if((int)$amt > 0):
										if($subhead_row['deduction'] == 1):
											$credit_amt = $amt;
											$debit_amt = '0.00';
										else:
											$credit_amt = '0.00';
											$debit_amt = $amt;
										endif;
										$tdtlsdata = array(
												'transaction' => $result,
												'location' => $loc_row['location_id'],
												'activity' => $act_row['activity_id'],
												'head' => $subhead_row['head_id'],
												'sub_head' => $subhead_row['id'],
												'bank_ref_type' => '',
												'cheque_no' => '',
												'debit' => $debit_amt,
												'credit' => $credit_amt,
												'ref_no'=> '',
												'type' => '2',//system generated data
												'author' =>$this->_author,
												'created' =>$this->_created,
												'modified' =>$this->_modified,
										);
										$tdtlsdata = $this->_safedataObj->rteSafe($tdtlsdata);
										$result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdtlsdata);
										if($result2 <= 0):
											break;
										endif;
									endif;
								endforeach;
								if($result2 <= 0):
									break;
								endif;
							endforeach;
							if($result2 <= 0):
								break;
							endif;
						endforeach;
						
						$sh_data2 = array(
							'year' => $data['year'],
							'month' => $data['month'],
							'location' => '-1',
							'activity' => '-1',
							'deduction' => '1',
						);
						$subheads2 = $this->getDefinedTable(Hr\PayrollTable::class)->salaryBookingSubhead($sh_data2);
						foreach($subheads2 as $subhead_row2):
							$filter2 = array(
								'year' => $sh_data2['year'],
								'month' => $sh_data2['month'],
								'location' => '-1',
								'activity' => '-1',
								'subhead' => $subhead_row2['ref_id'],
								'region' => '-1',
								'department' => '-1',													
							);
							$amt2 = $this->getDefinedTable(Hr\PaydetailTable::class)->getAmtforSummary($filter2);
							
							if((int)$amt2 > 0):
								if($subhead_row2['deduction'] == 1):
									$credit_amt2 = $amt2;
									$debit_amt2 = '0.00';
								else:
									$credit_amt2 = '0.00';
									$debit_amt2 = $amt2;
								endif;
								$tdtlsdata2 = array(
										'transaction' => $result,
										'location' => '7',
										'activity' => '5',
										'head' => $subhead_row2['head_id'],
										'sub_head' => $subhead_row2['id'],
										'bank_ref_type' => '',
										'cheque_no' => '',
										'debit' => $debit_amt2,
										'credit' => $credit_amt2,
										'ref_no'=> '',
										'type' => '2',//system generated data
										'author' =>$this->_author,
										'created' =>$this->_created,
										'modified' =>$this->_modified,
								);
								$tdtlsdata2 = $this->_safedataObj->rteSafe($tdtlsdata2);
								$result4 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdtlsdata2);
								if($result4 <= 0):
									break;
								endif;
							endif;
						endforeach;
						if($result2 > 0 && $result4 >0):
							//insert into transactiondetail table from form
							$location= $form['location'];
							$activity= $form['activity'];
							$head= $form['head'];
							$sub_head= $form['sub_head'];
							$cheque_no= $form['cheque_no'];
							$debit= $form['debit'];
							$credit= $form['credit'];
							for($i=0; $i < sizeof($activity); $i++):
								if(isset($activity[$i]) && is_numeric($activity[$i])):
									$tdetailsdata = array(
											'transaction' => $result,
											'location' => $location[$i],
											'activity' => $activity[$i],
											'head' => $head[$i],
											'sub_head' => $sub_head[$i],
											'bank_ref_type' => '',
											'cheque_no' => $cheque_no[$i],
											'debit' => (isset($debit[$i]))? $debit[$i]:'0.00',
											'credit' => (isset($credit[$i]))? $credit[$i]:'0.00',
											'ref_no'=> '',
											'type' => '1',//user inputted  data
											'author' =>$this->_author,
											'created' =>$this->_created,
											'modified' =>$this->_modified,
									);
									$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
									$result3 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
									if($result3 <= 0):
										break;
									endif;
								endif;
							endfor;
							if($result3 > 0):
								$this->_connection->commit(); // commit transaction on success
								$this->flashMessenger()->addMessage("success^ New Transaction successfully added | ".$voucher_no);
								return $this->redirect()->toRoute('transaction', array('action' =>'viewtransaction', 'id' => $result));
							else:
								$this->_connection->rollback(); // rollback the transaction on failure
								$this->flashMessenger()->addMessage("error^ Failed to book salary to transaction");
							endif;
						else:
							$this->_connection->rollback(); // rollback the transaction on failure
							$this->flashMessenger()->addMessage("error^ Failed to book salary to transaction");
						endif;
					else:
						$this->_connection->rollback(); // rollback the transaction on failure
						$this->flashMessenger()->addMessage("error^ Failed to book salary to transaction");
					endif;
				else:
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ New Transaction successfully added | ".$voucher_no);
					return $this->redirect()->toRoute('transaction', array('action' =>'viewtransaction', 'id' => $result));
				endif;
				return $this->redirect()->toRoute('payroll', array('action'=>'payroll', 'id'=> $form['year'].'-'.$form['month']));
			else:
				if(isset($form['year']) && isset($form['month'])):
					//check if all the payheads have subheads for booking
					$payhead_types = $this->getDefinedTable(Hr\PayheadtypeTable::class)->getNotIn();
					$data = array(
							'year' => $form['year'],
							'month' => $form['month'],
					);			
					$locations = $this->getDefinedTable(Hr\PayrollTable::class)->salaryBookingLocation($data);
					return new ViewModel(array(
						'title'  => 'Salary Booking',
						'data' => $data,
						'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
						'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
						'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
						'journals' => $this->getDefinedTable(Accounts\JournalTable::class)->getAll(),
						'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
						'heads' => $this->getDefinedTable(Accounts\HeadTable::class)->getAll(),
						'locations' => $locations,
						'payrollObj' => $this->getDefinedTable(Hr\PayrollTable::class),
						'paydetailObj' => $this->getDefinedTable(Hr\PaydetailTable::class),
						'payhead_types' => $payhead_types,
					));				
				endif;
			endif;
		endif;
		$this->flashMessenger()->addMessage("error^ Failed to book salary to transaction");	
		
		return $this->redirect()->toRoute('payroll', array('action'=>'payroll', 'id'=> $form['year'].'-'.$form['month']));
	}
	/**
	 * Monthly salary booking in transaction
	 */
	public function bookadvancesalaryAction()
	{
		$this->init();
		
		$id_parts = !empty($this->_id) ? explode('-', $this->_id) : array('0', '0');
		list($year, $month) = $id_parts;
		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			// booking to transaction
			if(isset($form['voucher_date']) && isset($form['voucher_amount'])):
				//generate voucher no
				$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
				$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn($form['voucher_type'],'prefix');
				$date = date('ym',strtotime($form['voucher_date']));
				$tmp_VCNo = $loc.$prefix.$date;
				
				$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
				
				$pltp_no_list = array();
				foreach($results as $result):
					array_push($pltp_no_list, substr($result['voucher_no'], 8));
				endforeach;
				$next_serial = max($pltp_no_list) + 1;
				switch(strlen($next_serial)){
					case 1: $next_dc_serial = "000".$next_serial; break;
					case 2: $next_dc_serial = "00".$next_serial;  break;
					case 3: $next_dc_serial = "0".$next_serial;   break;
					default: $next_dc_serial = $next_serial;       break;
				}	
				$voucher_no = $tmp_VCNo.$next_dc_serial;
				
				$data1 = array(
						'voucher_date' => $form['voucher_date'],
						'voucher_type' => $form['voucher_type'],
						'doc_id' => $form['doc_id'],
						'doc_type' => $form['doc_type'],
						'voucher_no' => $voucher_no,
						'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
						'remark' => $form['remark'],
						'status' => '3',
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
				);
				$data1 = $this->_safedataObj->rteSafe($data1);
				$this->_connection->beginTransaction(); //***Transaction begins here***//
				$result = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
				if($result > 0):
					//insert into salarybooking table
					$sb_data = array(
							'transaction' => $result,
							'year' => $form['year'],
							'month' => $form['month'],
							'salary_advance' => '2',
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
					);
					$result1 = $this->getDefinedTable(Hr\SalarybookingTable::class)->save($sb_data);
					if($result1 > 0):
						//insert into transactiondetail table from payroll table
						$data = array(
							'year' => $form['year'],
							'month' => $form['month'],
						);			
						$locations = $this->getDefinedTable(Hr\PayrollTable::class)->salaryBookingLocation($data);
						
						foreach($locations as $loc_row):
							$activities = $this->getDefinedTable(Hr\PayrollTable::class)->salaryBookingActivity($loc_row['location_id']);
							foreach($activities as $act_row):
								$sh_data = array(
									'year' => $data['year'],
									'month' => $data['month'],
									'location' => $loc_row['location_id'],
									'activity' => $act_row['activity_id'],
								);
								$subheads = $this->getDefinedTable(Hr\PayrollTable::class)->salaryAdvanceSubhead($sh_data);
								foreach($subheads as $subhead_row):
									$payroll_id = $this->getDefinedTable(Hr\PayrollTable::class)->getColumn(array('employee' =>$subhead_row['ref_id'],'year'=>$data['year'],'month'=>$data['month']),'id'); 	
									$amt = $this->getDefinedTable(Hr\PaydetailTable::class)->getColumn(array('pay_roll'=>$payroll_id,'pay_head'=>'12'),'amount');
									
									if((int)$amt > 0):
										$credit_amt = $amt;
										$debit_amt = '0.00';
										
										$tdtlsdata = array(
												'transaction' => $result,
												'location' => $loc_row['location_id'],
												'activity' => $act_row['activity_id'],
												'head' => $subhead_row['head_id'],
												'sub_head' => $subhead_row['id'],
												'bank_ref_type' => '',
												'cheque_no' => '',
												'debit' => $debit_amt,
												'credit' => $credit_amt,
												'ref_no'=> '',
												'type' => '2',//system generated data
												'author' =>$this->_author,
												'created' =>$this->_created,
												'modified' =>$this->_modified,
										);
										
										$tdtlsdata = $this->_safedataObj->rteSafe($tdtlsdata);
										$result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdtlsdata);
									endif;
								endforeach;
							endforeach;
						endforeach;
						
						if($result2 > 0):
							//insert into transactiondetail table from form
							$location= $form['location'];
							$activity= $form['activity'];
							$head= $form['head'];
							$sub_head= $form['sub_head'];
							$cheque_no= $form['cheque_no'];
							$debit= $form['debit'];
							$credit= $form['credit'];
							for($i=0; $i < sizeof($activity); $i++):
								if(isset($activity[$i]) && is_numeric($activity[$i])):
									$tdetailsdata = array(
											'transaction' => $result,
											'location' => $location[$i],
											'activity' => $activity[$i],
											'head' => $head[$i],
											'sub_head' => $sub_head[$i],
											'bank_ref_type' => '',
											'cheque_no' => $cheque_no[$i],
											'debit' => (isset($debit[$i]))? $debit[$i]:'0.00',
											'credit' => (isset($credit[$i]))? $credit[$i]:'0.00',
											'ref_no'=> '',
											'type' => '1',//user inputted  data
											'author' =>$this->_author,
											'created' =>$this->_created,
											'modified' =>$this->_modified,
									);
									$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
									$result3 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
								endif;
							endfor;
							if($result3 > 0):
								$this->_connection->commit(); // commit transaction on success
								$this->flashMessenger()->addMessage("success^ New Transaction successfully added | ".$voucher_no);
								return $this->redirect()->toRoute('transaction', array('action' =>'viewtransaction', 'id' => $result));
							else:
								$this->_connection->rollback(); // rollback the transaction on failure
								$this->flashMessenger()->addMessage("error^ Failed to book salary to transaction. Please transaction details");
							endif;
						else:
							$this->_connection->rollback(); // rollback the transaction on failure
							$this->flashMessenger()->addMessage("error^ Failed to book advance salary to transaction. Please check transaction details.");
						endif;
					else:
						$this->_connection->rollback(); // rollback the transaction on failure
						$this->flashMessenger()->addMessage("error^ Failed to book advance salary to transaction. Please Check the transaction year and month.");
					endif;
				else:
					$this->_connection->rollback(); // rollback the transaction on failure
					$this->flashMessenger()->addMessage("error^ Failed to book advance salary to transaction. Please check transaction fields");
				endif;
			else:
				$this->_connection->rollback(); // rollback the transaction on failure
				$this->flashMessenger()->addMessage("error^ Failed to book advance salary to transaction. Please check voucher date and amount.");
			endif;
			return $this->redirect()->toRoute('payroll', array('action'=>'payroll', 'id'=> $form['year'].'-'.$form['month']));
		else:
			if(isset($year) && isset($month)):
				$data = array(
						'year' => $year,
						'month' => $month,
				);			
				$locations = $this->getDefinedTable(Hr\PayrollTable::class)->salaryBookingLocation($data);
				return new ViewModel(array(
					'title'  => 'Advance Salary Booking',
					'data' => $data,
					'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
					'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
					'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
					'journals' => $this->getDefinedTable(Accounts\JournalTable::class)->getAll(),
					'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
					'heads' => $this->getDefinedTable(Accounts\HeadTable::class)->getAll(),
					'locations' => $locations,
					'payrollObj' => $this->getDefinedTable(Hr\PayrollTable::class),
					'paydetailObj' => $this->getDefinedTable(Hr\PaydetailTable::class),
					'employeeObj'  => $this->getDefinedTable(Hr\EmployeeTable::class),
				));				
			endif;
		endif;
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
			$affected_ps = $this->getDefinedTable(Hr\PaystructureTable::Class)->get(array('sd.employee'=>$employee, 'ph.against'=> $payhead_id));
		else:
			$affected_ps = $this->getDefinedTable(Hr\PaystructureTable::Class)->get(array('sd.employee'=>$employee, 'ph.against'=> array($payhead_id,'-1','-2')));
		endif;

        $againstGrossPH = array(); 
        $againstPitNet = array(); 
		foreach($affected_ps as $aff_ps):
			if($aff_ps['against'] == '-1'):
				array_push($againstGrossPH, $aff_ps);
				//$base_amount = $this->getDefinedTable(Hr\TempPayrollTable::class)->getColumn(array('employee'=>$employee),'gross');
			elseif($aff_ps['against'] == '-2'):
				array_push($againstPitNet, $aff_ps);
			else:
				$base_amount = $this->getDefinedTable(Hr\PaystructureTable::Class)->getColumn(array('employee'=>$employee, 'pay_head'=>$aff_ps['against']),'amount');
			endif;

			if($aff_ps['type'] == 2 && $aff_ps['against'] != '-1' && $aff_ps['against'] != '-2'):				
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
				$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->save($data);
		    elseif($aff_ps['type'] == 3 && $aff_ps['against'] != '-1' && $aff_ps['against'] != '-2'):	
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
				$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->save($data);
			endif;
		endforeach;
		
		//making changes to temp payroll
		foreach($this->getDefinedTable(Hr\TempPayrollTable::class)->get(array('pr.employee' => $employee)) as $temp_payroll);				
		$total_earning = 0;		
		$total_deduction = 0;
		$total_actual_earning = 0;
		$total_actual_deduction = 0;
		foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get(array('sd.employee' => $employee, 'pht.deduction'=>'1')) as $paydetails):
			if($paydetails['dlwp']==1):
				$amount = $paydetails['amount'] - ($paydetails['amount']/$temp_payroll['working_days']) * $temp_payroll['leave_without_pay'];
			else:
				$amount = $paydetails['amount'];
			endif;
			$total_deduction = $total_deduction + $amount;
			$total_actual_deduction = $total_actual_deduction + $paydetails['amount'];
		endforeach;	
		foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get(array('sd.employee' => $employee, 'pht.deduction'=>'0')) as $paydetails):
			if($paydetails['dlwp']==1):
				$amount = $paydetails['amount'] - ($paydetails['amount']/$temp_payroll['working_days']) * $temp_payroll['leave_without_pay'];
			else:
				$amount = $paydetails['amount'];
			endif;
			$total_earning = $total_earning + $amount;
			$total_actual_earning = $total_actual_earning + $paydetails['amount'];
		endforeach;				
		$leave_encashment = $temp_payroll['leave_encashment'];
		$bonus = $temp_payroll['bonus'];
		$net_pay = $total_earning + $leave_encashment + $bonus - $total_deduction;
		$earning_dlwp = $total_actual_earning - $total_earning;
		$deduction_dlwp = $total_actual_deduction - $total_deduction;
		$data1 = array(
				'id'	=> $temp_payroll['id'],
				'gross' => $total_actual_earning,
				'total_deduction' => $total_actual_deduction,
				'net_pay' => $net_pay,
				'earning_dlwp' => $earning_dlwp,
				'deduction_dlwp' => $deduction_dlwp,
				'status' => '1', // initiated
				'author' =>$this->_author,
				'modified' =>$this->_modified,
		);	
			//echo "<pre>";print_r($data1);exit;
		$data1 = $this->_safedataObj->rteSafe($data1);
		$result1 = $this->getDefinedTable(Hr\TempPayrollTable::class)->save($data1);
		if($result1):
			if(sizeof($againstGrossPH)>0){
			   foreach($againstGrossPH as $aff_ps):
				   $base_amount = $this->getDefinedTable(Hr\TempPayrollTable::class)->getColumn(array('employee'=>$employee),'gross');
				   if($aff_ps['type'] == 2){
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
						$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->save($data);
				   }
				   elseif($aff_ps['type'] == 3){
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
						$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->save($data);
				   }
			   endforeach;
			}
			if(sizeof($againstPitNet)>0){
			   foreach($againstPitNet as $aff_ps):
				   $Gross_amount = $this->getDefinedTable(Hr\TempPayrollTable::class)->getColumn(array('employee'=>$employee),'gross');
				   $PFDed = $this->getDefinedTable(Hr\PaystructureTable::Class)->getColumn(array('employee'=>$employee, 'pay_head'=>7),'amount');
				   $GISDed = $this->getDefinedTable(Hr\PaystructureTable::Class)->getColumn(array('employee'=>$employee, 'pay_head'=>6),'amount');
				   $base_amount = $Gross_amount - $PFDed - $GISDed;
				   if($aff_ps['type'] == 2){
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
						$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->save($data);
				   }
				   elseif($aff_ps['type'] == 3){
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
						$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->save($data);
				   }
			   endforeach;
			}
          return $result1;
		endif; 		
	}
	
	/*
	 * Action to add pay structure
	**/
	public function paystructureAction()
	{
		$this->init();

		return new ViewModel(array(
				'title' => 'Pay Structure',
				'id' => $this->_id,
				'employee' => $this->getDefinedTable(Hr\EmployeeTable::class)->get($this->_id),
				'emphistoryObj' => $this->getDefinedTable(Hr\EmpHistoryTable::class),
				'pay_heads' => $this->getDefinedTable(Hr\PayheadTable::class)->getAll(),
				'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
				'paystructure' => $this->getDefinedTable(Hr\PaystructureTable::Class)->get(array('employee' => $this->_id)),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::Class),

		));
	}
	
	/**
	 * Ajax to get the employee according to location
	**/
	public function getemployeeAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getPost();
		
		$location_id = $form['location'];
		$employees = $this->getDefinedTable(Hr\EmployeeTable::class)->get(array('e.location'=>$location_id,'e.status'=>array(1,4,5)));
		
		$emp .="<option value='-1'>All</option>";
		foreach($employees as $employee):
			$emp .="<option value='".$employee['id']."'>".$employee['full_name']."</option>";
		endforeach;
		echo json_encode(array(
				'emp' => $emp,
		));
		exit;
	}
}

