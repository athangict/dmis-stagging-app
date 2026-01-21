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

class PayIncrementController extends AbstractActionController
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
					'title' => 'Pay Increment',
					'pay_increment' => $this->getDefinedTable(Hr\PayIncrementTable::class)->getAll(),
					'increment_typeObj' => $this->getDefinedTable(Hr\IncrementTypeTable::class),
		));	
	}
	/**
	 * Add Pay Increment
	**/
	public function addincrementAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			/*** Generate Pay Increment No ***/
			$date = date('ym',strtotime($form['increment_date']));					
			$temp_no = "IN".$date; 			
			$results = $this->getDefinedTable(Hr\PayIncrementTable::class)->getMonthlyNo($temp_no);
			$no_list = array();
            foreach($results as $result):
	       		array_push($no_list, substr($result['increment_no'], 6)); 
		   	endforeach;
            $next_serial = max($no_list) + 1;
			switch(strlen($next_serial)){
				case 1: $next_serial_no = "000".$next_serial; break;
			    case 2: $next_serial_no = "00".$next_serial;  break;
			    case 3: $next_serial_no = "0".$next_serial;   break;
			   	default: $next_serial_no = $next_serial;       break; 
			}					   
			$increment_no = $temp_no.$next_serial_no;
			
			$increment_data = array(
						'increment_no'   => $increment_no,
						'increment_date' => $form['increment_date'],
						'increment_type' => $form['increment_type'],
						'status'         => '1',  
						'author'         => $this->_author,
						'created'        => $this->_created,
						'modified'       => $this->_modified,
			);
			$increment_data   = $this->_safedataObj->rteSafe($increment_data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Hr\PayIncrementTable::class)->save($increment_data);
			
			if($result):
				if($form['increment_type'] == '1'): //Manual Increment
					$increment_check = $form['increment_check'];
					foreach($increment_check as $row):
						$employee = $form['employee_'.$row];
						$old_pay = $form['old_pay_'.$row];
						$increment = $form['incrementM_'.$row];
						$new_pay = $form['new_payM_'.$row];
						$detail_data = array(
								'pay_increment' => $result,
								'employee'      => $employee,
								'old_pay'       => $old_pay,
								'increment'     => $increment,
								'new_pay'       => $new_pay,
								'author'        => $this->_author,
								'created'       => $this->_created,
								'modified'      => $this->_modified,
						);
						$detail_data = $this->_safedataObj->rteSafe($detail_data);
						$detail_result = $this->getDefinedTable(Hr\PayIncrementDtlTable::class)->save($detail_data);
					endforeach;
				else:
					$increment_check = $form['increment_check'];
					foreach($increment_check as $row):
						$emplevel = $this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($row,'position_level');
						$positionlevels = $this->getDefinedTable(Hr\PositionlevelTable::class)->get($emplevel);
$level = null; // Initialize to avoid undefined variable warning
						foreach($positionlevels as $row):
							$level = $row;
						endforeach;
						$basic_pay = $this->getDefinedTable(Hr\PaystructureTable::class)->getColumn(array('employee' => $row, 'pay_head'=> '1'),'amount');
						$new_pay = $basic_pay + $level['increment'];
						$detail_data = array(
								'pay_increment' => $result,
								'employee'      => $row,
								'old_pay'       => $basic_pay,
								'increment'     => $level['increment'],
								'new_pay'       => $new_pay,
								'author'        => $this->_author,
								'created'       => $this->_created,
								'modified'      => $this->_modified,
						);
						$detail_data = $this->_safedataObj->rteSafe($detail_data);
						$detail_result = $this->getDefinedTable(Hr\PayIncrementDtlTable::class)->save($detail_data);
					endforeach;
				endif;//end of if($increment_type == '1')
			endif;//end of if($result)
			if($detail_result):
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Successfully added new pay increment");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage('error^ Unsuccessful to add new pay increment'); 
			endif;
			return $this->redirect()->toRoute('payincrement', array('action'=>'viewincrement','id'=>$result)); 
		endif;
		
		return new ViewModel(array(
					'title' => 'Add Pay Increment',
					'increments' => $this->getDefinedTable(Hr\IncrementTypeTable::class)->getAll(),
		));
	}
	/**
	 * Get Pay Increment Details
	**/
	public function getincrementdtlAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getPost();
		$increment_type = $form['increment_type'];
		
		$ViewModel = new ViewModel(array(
					'increment_type' => $increment_type,
					'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
					'positionlevelObj' => $this->getDefinedTable(Hr\PositionlevelTable::class),
					'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::class),
					'incrementObj' => $this->getDefinedTable(Hr\IncrementTypeTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * View Pay Increment
	**/
	public function viewincrementAction()
	{
		$this->init();
		
		return new ViewModel(array(
					'title' => 'View Pay Increment',
					'increments' => $this->getDefinedTable(Hr\PayIncrementTable::class)->get($this->_id),
					'increment_dtls' => $this->getDefinedTable(Hr\PayIncrementDtlTable::class)->get(array('pay_increment' => $this->_id)),
					'incrementtypeObj' => $this->getDefinedTable(Hr\IncrementTypeTable::class),
					'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
					'positionlevelObj' => $this->getDefinedTable(Hr\PositionlevelTable::class),
		));
	}
	/**
	 * Edit Pay Increment
	**/
	public function editincrementAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			
			$increment_data = array(
						'id'             => $form['increment_id'],
						'increment_date' => $form['increment_date'],
						'increment_type' => $form['increment_type'],
						'status'         => '1',  
						'author'         => $this->_author,
						//'created'        => $this->_created,
						'modified'       => $this->_modified,
			);
			$increment_data   = $this->_safedataObj->rteSafe($increment_data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Hr\PayIncrementTable::class)->save($increment_data);
			
			if($result):
				if($form['increment_type'] == '1'): //Manual Increment
					$increment_check = $form['increment_check'];
					$excluded_emps = $this->getDefinedTable(Hr\PayIncrementDtlTable::class)->getNotIn($increment_check, array('pay_increment'=>$form['increment_id']));
					foreach($increment_check as $row):
						$detail = $form['detail_'.$row];
						$employee = $form['employee_'.$row];
						$old_pay = $form['old_pay_'.$row];
						$increment = $form['incrementM_'.$row];
						$new_pay = $form['new_payM_'.$row];
						if(isset($detail) && $detail > 0):
							$detail_data = array(
									'id'            => $detail,
									'pay_increment' => $result,
									'employee'      => $employee,
									'old_pay'       => $old_pay,
									'increment'     => $increment,
									'new_pay'       => $new_pay,
									'author'        => $this->_author,
									//'created'       => $this->_created,
									'modified'      => $this->_modified,
							);
						else:
							$detail_data = array(
									'pay_increment' => $result,
									'employee'      => $employee,
									'old_pay'       => $old_pay,
									'increment'     => $increment,
									'new_pay'       => $new_pay,
									'author'        => $this->_author,
									'created'       => $this->_created,
									'modified'      => $this->_modified,
							);
						endif;
						$detail_data = $this->_safedataObj->rteSafe($detail_data);
						$detail_result = $this->getDefinedTable(Hr\PayIncrementDtlTable::class)->save($detail_data);
					endforeach;
					foreach($excluded_emps as $clear):
						$this->getDefinedTable(Hr\PayIncrementDtlTable::class)->remove($clear['id']);
					endforeach;
				else:
					$increment_check = $form['increment_check'];
					$excluded_emps = $this->getDefinedTable(Hr\PayIncrementDtlTable::class)->getNotIn($increment_check, array('pay_increment'=>$form['increment_id']));
					foreach($increment_check as $row):
						$detail = $form['detail_'.$row];
						$emplevel = $this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($row,'position_level');
						$positionlevels = $this->getDefinedTable(Hr\PositionlevelTable::class)->get($emplevel);
$level = null; // Initialize to avoid undefined variable warning
						foreach($positionlevels as $row):
							$level = $row;
						endforeach;
						$basic_pay = $this->getDefinedTable(Hr\PaystructureTable::class)->getColumn(array('employee' => $row, 'pay_head'=> '1'),'amount');
						$new_pay = $basic_pay + $level['increment'];
						
						if(isset($detail) && $detail > 0):
							$detail_data = array(
									'id'            => $detail,
									'pay_increment' => $result,
									'employee'      => $row,
									'old_pay'       => $basic_pay,
									'increment'     => $level['increment'],
									'new_pay'       => $new_pay,
									'author'        => $this->_author,
									//'created'       => $this->_created,
									'modified'      => $this->_modified,
							);
						else:
							$detail_data = array(
								'pay_increment' => $result,
								'employee'      => $row,
								'old_pay'       => $basic_pay,
								'increment'     => $level['increment'],
								'new_pay'       => $new_pay,
								'author'        => $this->_author,
								'created'       => $this->_created,
								'modified'      => $this->_modified,
							);
						endif;
						$detail_data = $this->_safedataObj->rteSafe($detail_data);
						//echo"<pre>";print_r($detail_data);
						$detail_result = $this->getDefinedTable(Hr\PayIncrementDtlTable::class)->save($detail_data);
					endforeach;
					//exit;
					foreach($excluded_emps as $clear):
						$this->getDefinedTable(Hr\PayIncrementDtlTable::class)->remove($clear['id']);
					endforeach;
				endif;//end of if($increment_type == '1')
			endif;//end of if($result)
			if($detail_result):
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Successfully updated pay increment");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage('error^ Unsuccessful to update pay increment'); 
			endif;
			return $this->redirect()->toRoute('payincrement', array('action'=>'viewincrement','id'=>$form['increment_id'])); 
		endif;
		return new ViewModel(array(
					'title' => 'Edit Pay Increment',
					'incrementtypeObj' => $this->getDefinedTable(Hr\IncrementTypeTable::class),
					'increments' => $this->getDefinedTable(Hr\PayIncrementTable::class)->get($this->_id),
					'increment_dtls' => $this->getDefinedTable(Hr\PayIncrementDtlTable::class)->get(array('pay_increment' => $this->_id)),
					'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
					'positionlevelObj' => $this->getDefinedTable(Hr\PositionlevelTable::class),
					'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::class),
		));
	}
	/**
	 * Cancel the Pay Increment
	**/
	public function cancelincrementAction()
	{
		$this->init();
		
		$increment_data = array(
					'id'             => $this->_id,
					'status'         => '4',  
					'author'         => $this->_author,
					'modified'       => $this->_modified,
		);
		$increment_data   = $this->_safedataObj->rteSafe($increment_data);
		$this->_connection->beginTransaction(); //***Transaction begins here***//
		$result = $this->getDefinedTable(Hr\PayIncrementTable::class)->save($increment_data);
		if($result):
			$this->_connection->commit(); // commit transaction on success
			$this->flashMessenger()->addMessage("success^ Successfully cancelled pay increment");
		else:
			$this->_connection->rollback(); // rollback transaction over failure
			$this->flashMessenger()->addMessage('error^ Unsuccessful to cancell pay increment'); 
		endif;
		return $this->redirect()->toRoute('payincrement', array('action'=>'viewincrement','id'=>$this->_id)); 
	}
	/**
	 * Commit the Pay Increment
	**/
	public function commitincrementAction()
	{
		$this->init();
		//effect in pay structure
		$employees = $this->getDefinedTable(Hr\PayIncrementDtlTable::class)->get(array('pay_increment' => $this->_id));
		foreach($employees as $employee):
			$ps_id = $this->getDefinedTable(Hr\PaystructureTable::class)->getColumn(array('employee' => $employee['employee'], 'pay_head' => '1'),'id');
			$roundup = $this->getDefinedTable(Hr\PayheadTable::class)->getColumn('1', 'roundup');
			if($roundup == 1):
				$employee['new_pay'] = round($employee['new_pay']);
			endif;
			$data = array(
					'id' => $ps_id,
					'amount' => $employee['new_pay'],
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Hr\PaystructureTable::class)->save($data);
			
			if($result > 0):
				//changes in paystructure should affect other payheads and temporary payroll
				foreach($this->getDefinedTable(Hr\PaystructureTable::class)->get($ps_id) as $row);				
				$result1 = $this->calculatePayheadAmount($row);	
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ Pay detail successfully Updated");
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to Update pay detail");
				endif;
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to Update Basic Pay");
			endif;	
		endforeach;
		if($result1):
			$increment_data = array(
						'id'             => $this->_id,
						'commit_date'    => date('Y-m-d'),
						'status'         => '3',  
						'author'         => $this->_author,
						'modified'       => $this->_modified,
			);
			$increment_data = $this->_safedataObj->rteSafe($increment_data);
			$result = $this->getDefinedTable(Hr\PayIncrementTable::class)->save($increment_data);
			if($result):
				//$this->_connection->commit(); 
				$this->flashMessenger()->addMessage("success^ Successfully committed pay increment");
			else:
				$this->_connection->rollback(); 
				$this->flashMessenger()->addMessage('error^ Unsuccessful to commit pay increment'); 
			endif;
		else:
			//$this->_connection->rollback(); 
			$this->flashMessenger()->addMessage('error^ Unsuccessful to commit pay increment'); 
		endif;
		return $this->redirect()->toRoute('payincrement', array('action'=>'viewincrement','id'=>$this->_id)); 
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

        $againstGrossPH = array(); 
        $againstPitNet = array(); 
		foreach($affected_ps as $aff_ps):
			if($aff_ps['against'] == '-1'):
				array_push($againstGrossPH, $aff_ps);
				//$base_amount = $this->getDefinedTable(Hr\TempPayrollTable::class)->getColumn(array('employee'=>$employee),'gross');
			elseif($aff_ps['against'] == '-2'):
				array_push($againstPitNet, $aff_ps);
			else:
				$base_amount = $this->getDefinedTable(Hr\PaystructureTable::class)->getColumn(array('employee'=>$employee, 'pay_head'=>$aff_ps['against']),'amount');
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
				$result = $this->getDefinedTable(Hr\PaystructureTable::class)->save($data);
		    elseif($aff_ps['type'] == 3 && $aff_ps['against'] != '-1' && $aff_ps['against'] != '-2'):	
				$rate=0;  $base=0;  $value=0;  $min=0;
				foreach($this->getDefinedTable(Hr\PayslabTable::class)->get(array('pay_head' => $aff_ps['pay_head_id'])) as $payslab):
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
		foreach($this->getDefinedTable(Hr\TempPayrollTable::class)->get(array('pr.employee' => $employee)) as $temp_payroll);				
		$total_earning = 0;		
		$total_deduction = 0;
		$total_actual_earning = 0;
		$total_actual_deduction = 0;
		foreach($this->getDefinedTable(Hr\PaystructureTable::class)->get(array('sd.employee' => $employee, 'pht.deduction'=>'1')) as $paydetails):
			if($paydetails['dlwp']==1):
				$amount = $paydetails['amount'] - ($paydetails['amount']/$temp_payroll['working_days']) * $temp_payroll['leave_without_pay'];
			else:
				$amount = $paydetails['amount'];
			endif;
			$total_deduction = $total_deduction + $amount;
			$total_actual_deduction = $total_actual_deduction + $paydetails['amount'];
		endforeach;	
		foreach($this->getDefinedTable(Hr\PaystructureTable::class)->get(array('sd.employee' => $employee, 'pht.deduction'=>'0')) as $paydetails):
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
						$result = $this->getDefinedTable(Hr\PaystructureTable::class)->save($data);
				   }
				   elseif($aff_ps['type'] == 3){
					 $rate=0;  $base=0;  $value=0;  $min=0;
						foreach($this->getDefinedTable(Hr\PayslabTable::class)->get(array('pay_head' => $aff_ps['pay_head_id'])) as $payslab):
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
				   }
			   endforeach;
			}
			if(sizeof($againstPitNet)>0){
			   foreach($againstPitNet as $aff_ps):
				   $Gross_amount = $this->getDefinedTable(Hr\TempPayrollTable::class)->getColumn(array('employee'=>$employee),'gross');
				   $PFDed = $this->getDefinedTable(Hr\PaystructureTable::class)->getColumn(array('employee'=>$employee, 'pay_head'=>7),'amount');
				   $GISDed = $this->getDefinedTable(Hr\PaystructureTable::class)->getColumn(array('employee'=>$employee, 'pay_head'=>6),'amount');
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
						$result = $this->getDefinedTable(Hr\PaystructureTable::class)->save($data);
				   }
				   elseif($aff_ps['type'] == 3){
					 $rate=0;  $base=0;  $value=0;  $min=0;
						foreach($this->getDefinedTable(Hr\PayslabTable::class)->get(array('pay_head' => $aff_ps['pay_head_id'])) as $payslab):
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
				   }
			   endforeach;
			}
          return $result1;
		endif; 		
	}
}
?>

