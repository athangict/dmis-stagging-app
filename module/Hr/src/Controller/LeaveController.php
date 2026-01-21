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

class LeaveController extends AbstractActionController
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
	 * indexAction
	**/
	public function indexAction()
	{
        $this->init();				
		return new ViewModel(array(
			'title'        => 'Leave Application',
			'userID'       => $this->_login_id,
			'leaveObj'     => $this->getDefinedTable(Hr\LeaveTable::class),
			'employeeObj'  => $this->getDefinedTable(Hr\EmployeeTable::class),
			'userObj'      => $this->getDefinedTable(Administration\UsersTable::class),
			'userRoleObj'  => $this->getDefinedTable(Acl\UserroleTable::class),
		));
	} 
	
	/**
	 *  myleave action
	 */
	public function employeeleaveAction()
	{
		$this->init();		
		return new ViewModel(array(
			'title' => 'Leave Application',
			'leave' => $this->getDefinedTable(Hr\LeaveTable::class)->get(array('employee'=>$this->_user->employee)),
			'leavedetailObj' => $this->getDefinedTable(Hr\LeaveDetailTable::class),
		));
	} 
	
	/**
	 *  apply action
	 */
	public function applyAction()
	{
		$this->init();	
		
        if($this->getRequest()->isPost()):		
			$form = $this->getRequest()->getPost()->toArray();	
			$pendingleaves = $this->getDefinedTable(Hr\LeaveTable::class)->get(array('ld.employee'=>$form['applicant'], 'ld.status'=>array(1,2,5,7)));
            if(sizeof($pendingleaves)>0){
				  $this->flashMessenger()->addMessage("notice^ Failed to apply leave because your previous leave is not complete");
				  return $this->redirect()->toRoute('leave');				
			}
			$authorEmpID = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author, 'employee');	
			$leaveOfficialResult = $this->getDefinedTable(Acl\UserroleTable::class)->get(array('user'=>$this->_author,'subrole'=>'17'));
			if($form['applicant'] != $authorEmpID && sizeof($leaveOfficialResult) > 0 ){	$leave_officier = $this->_author; }else{ $leave_officier = '0'; }		
			if($form['declaration'] == '1' && $form['applicant'] > 0 ):
			     $data = array(					
					'employee'     => $form['applicant'],
					'leave_type'   => $form['leave_type'],
					'start_date'   => $form['start_date'],
					'end_date'     => $form['end_date'],
					'no_of_days'   => $form['no_of_days'],
					'contact'      => $form['contact'],
					'delegation'   => $form['delegation'],
					'sanction_order_no'  => $form['sanction_order_no'],
					'actual_leave_taken'  => $form['no_of_days'],
					'remarks'  			 => $form['remarks'],
					'leave_official'  => $leave_officier,
					'remark_log'   => "",
					'status'       => '1',
					'author'        => $this->_author,
					'created'       => $this->_created,
					'modified'      => $this->_modified					
				);	
	            $data = $this->_safedataObj->rteSafe($data);
			    $result = $this->getDefinedTable(Hr\LeaveTable::class)->save($data);	
				if($result > 0){
				   $this->flashMessenger()->addMessage("success^ Leave application Save, Click Apply to Submit");
				   return $this->redirect()->toRoute('leave', array('action' => 'leavedetail', 'id'=>$result));
				}			
				else{
				  $this->flashMessenger()->addMessage("error^ Failed to submit leave application, Try again");
				  return $this->redirect()->toRoute('leave', array('action' => 'apply'));
				}				
			endif; 			
		endif;
		$viewModel = new ViewModel(array(
				'title'        => 'Leave Application',
				'userID'       => $this->_login_id,
				'login_emp_ID' => $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'employee'),
				'employeeObj'  => $this->getDefinedTable(Hr\EmployeeTable::class),
				'emphisObj'    => $this->getDefinedTable(Hr\EmpHistoryTable::class),
				'leaveTypes'   => $this->getDefinedTable(Hr\LeaveTypeTable::class)->getAll(),
				'userRoleObj'  => $this->getDefinedTable(Acl\UserroleTable::class),
				'userObj'      => $this->getDefinedTable(Administration\UsersTable::class),
				'leaveObj' 	   => $this->getDefinedTable(Hr\LeaveTable::class),
			));			
		return $viewModel;
	}
	
	/**
	 * get leave details
	 */
	 public function getleavedtlsAction(){
		$this->init();	
		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();
			
			$employeeID = $form['emp_id'];
			$leaveDate = $this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($employeeID, 'leave_balance_date');
			$leaveBal = $this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($employeeID, 'leave_balance');
			
			if($leaveDate > 0 || $leaveBal > 0):
				$startDate = $leaveDate;
				$Balance = $leaveBal;
			else:
				$startDate = $this->getDefinedTable(Hr\EmpHistoryTable::class)->getColumn(array('employee'=>$employeeID), 'start_date');
				$Balance = 0;
			endif; 
			$leaveObj = $this->getDefinedTable(Hr\LeaveTable::class);
			$minYr = date('Y',strtotime($startDate));
			$minMonth = date('m',strtotime($startDate));
			$curYr = date('Y');
			$curMonth = date('m');
			$casual = 0;  $earned = 0; 	$i = 1;	$j = 1;						
			for($curYear = $curYr; $curYear >= $minYr; $curYear--):			
				foreach($leaveObj->get(array('employee'=>$employeeID, 'ld.status'=>array(6,7))) as $leave):
					$startD = $leave['start_date'];
					$startYr = date('Y',strtotime($startD));
					$startMonth= date('m',strtotime($startD));							
					if($startYr == $curYear && $startMonth <= $curMonth):
						if($leave['leave_type']==2):
							$casual += $leave['actual_leave_taken'];
							if($i == 1):
								 $presentCasual = $casual;
							endif;// total casual leave used for till current year from given date															
							$i++;
						elseif($leave['leave_type']==3):
							$earned += $leave['actual_leave_taken'];//total earned leave used
							if($j == 1):
								$presentEarned = $earned;
							endif;// total casual leave used for till current year from given date															
							$j++;
						endif;	
					endif;
				endforeach;											 						 
			endfor;				
				$total_years = $curYr - $minYr;
				$sub_total = 0;
				$monthCasual = 10/12;
				if($total_years > 0){
					$Bmonth = (12 - $minMonth);  //addition of 1 is to include the present month 
					$Amonth = $curMonth;
					$sub_total = $Bmonth + $Amonth;
					$Bcasual = round($Bmonth * $monthCasual);
					
					if($total_years == 1):
						$total_months = $sub_total;							
						$totCasual = $Bcasual + 10;
						$usedcasual = $casual - $presentCasual;
						$CasualLeft = $Bcasual - $usedcasual;					
						$totcasualBal = $totCasual - $casual;
						$totearnedBal = $Balance + ($total_months * 2.5) - $earned;						
					else:
						$YEAR = $curYr - $minYr - 1;
						$total_months = ($YEAR * 12) + $sub_total;							
						$totCasual = $Bcasual + 10 + ($YEAR * 10);
						$casualBefore = $casual - $presentCasual;
						$CasualLeft = $Bcasual + ($YEAR * 10) - $casualBefore;						
						$totcasualBal = $totCasual - $casual;
						$totearnedBal = $Balance + ($total_months * 2.5) - $earned;									
					endif;
				}else{
					$total_months = $curMonth - $minMonth;
					$actCasual = (12 - $minMonth + 1) * $monthCasual;
					$CasualLeft = 0;
					$actualCasual = round($actCasual);
					$totcasualBal = $actualCasual - $casual;
					$totearnedBal = $Balance + ($total_months * 2.5) - $earned;
				}
			$casual_bal = 10 - $presentCasual; 
			$earned_bal = $totearnedBal + $CasualLeft;
			$leave_taken = $presentCasual + $presentEarned;
			$total_bal = $earned_bal + $casual_bal;
			if($total_bal > 90){
					$total_balance = 90;
			}else{
					$total_balance = $total_bal;
			}
			$no_of_days = '';
			echo json_encode(array(
					'casual_bal' => $casual_bal,
					'earned_bal' => $earned_bal,
					'total_leave'=> $leave_taken,
					'total_bal'	 => $total_balance,					
					'no_of_days' => $no_of_days,
			));
		exit;
	  endif;
	 }
	 
		/*leave application Detail*/
	public function leavedetailAction(){
		$this->init();
		$params = !empty($this->_id) ? explode("-", $this->_id) : array();
		if(isset($params['1']) && isset($params['2']) && $params['1'] == '1' && $params['2'] > 0){
			$flag = $this->getDefinedTable(Acl\NotifyTable::class)->getColumn($params['2'], 'flag'); 
			if($flag == "0") {
				$notify = array('id' => $params['2'], 'flag'=>'1');
               	$this->getDefinedTable(Acl\NotifyTable::class)->save($notify); 	
			}				
		}		
		$leaveID = $params['0'];
		$employeeID = $this->getDefinedTable(Hr\LeaveTable::class)->getColumn($leaveID, 'employee');
		$leaveDate = $this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($employeeID, 'leave_balance_date');
		$leaveBal = $this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($employeeID, 'leave_balance');
		
		if($leaveDate > 0 || $leaveBal > 0):
			$startDate = $leaveDate;
			$Balance = $leaveBal;
		else:
			$startDate = $this->getDefinedTable(Hr\EmpHistoryTable::class)->getColumn($employeeID, 'start_date');
			$Balance = 0;
		endif; 
		
		$leaveObj = $this->getDefinedTable(Hr\LeaveTable::class);
		$minYr = date('Y',strtotime($startDate));
		$minMonth = date('m',strtotime($startDate));
		$curYr = date('Y');
		$curMonth = date('m');
		$casual = 0;  $earned = 0; 	$i = 1;	$j = 1;						
		for($curYear = $curYr; $curYear >= $minYr; $curYear--):			
			foreach($leaveObj->get(array('employee'=>$employeeID, 'ld.status'=>array(6,7))) as $leave):
				$startD = $leave['start_date'];
				$startYr = date('Y',strtotime($startD));
				$startMonth= date('m',strtotime($startD));							
				if($startYr == $curYear && $startMonth <= $curMonth):
					if($leave['leave_type']==2):
						$casual += $leave['actual_leave_taken'];
						if($i == 1):
							 $presentCasual = $casual;
						endif;// total casual leave used for till current year from given date															
						$i++;
					elseif($leave['leave_type']==3):
						$earned += $leave['actual_leave_taken'];//total earned leave used
						if($j == 1):
							$presentEarned = $earned;
						endif;// total casual leave used for till current year from given date															
						$j++;
					endif;	
				endif;
			endforeach;											 						 
		endfor;				
			$total_years = $curYr - $minYr;
			$sub_total = 0;
			$monthCasual = 10/12;
			if($total_years > 0){
				$Bmonth = (12 - $minMonth); // addition of 1 is to include the present month 
				$Amonth = $curMonth;
				$sub_total = $Bmonth + $Amonth;
				$Bcasual = round($Bmonth * $monthCasual);
				
				if($total_years == 1):
					$total_months = $sub_total;							
					$totCasual = $Bcasual + 10;
					$usedcasual = $casual - $presentCasual;
					$CasualLeft = $Bcasual - $usedcasual;					
					$totcasualBal = $totCasual - $casual;
					$totearnedBal = $Balance + ($total_months * 2.5) - $earned;						
				else:
					$YEAR = $curYr - $minYr;
					$total_months = ($YEAR * 12) + $sub_total;							
					$totCasual = $Bcasual + 10 + ($YEAR * 10);
					$casualBefore = $casual - $presentCasual;
					$CasualLeft = $Bcasual + ($YEAR * 10) - $casualBefore;						
					$totcasualBal = $totCasual - $casual;
					$totearnedBal = $Balance + ($total_months * 2.5) - $earned;									
				endif;
			}else{
				//$total_months = $curMonth - $minMonth + 1;
				$total_months = $curMonth - $minMonth;
				$actCasual = (12 - $minMonth + 1) * $monthCasual;
				$CasualLeft = 0;
				$actualCasual = round($actCasual);
				$totcasualBal = $actualCasual - $casual;
				$totearnedBal = $Balance + ($total_months * 2.5) - $earned;
			}		 
		$casual_bal = 10 - $presentCasual;
	    $earned_bal = $totearnedBal + $CasualLeft; 
		$leave_taken = $presentCasual + $presentEarned;		
		$total_bal = $earned_bal + $casual_bal;
		if($total_bal > 90){
				$total_balance = 90;
		}else{
				$total_balance = $total_bal;
		}
				
		if($this->getRequest()->isPost()):		
			$form = $this->getRequest()->getPost()->toArray();
			if($form['cancel'] == "1"){
				$data = array(		
					 'id'       => $form['application_id'],
					 'status'   => '4',
					 'remark_log'  => "Leave Application Cancelled",
					 'author'      => $this->_author,
					 'modified'	   => $this->_modified								 
					 );
			}
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\LeaveTable::class)->save($data);	
			if($result){
				   $this->flashMessenger()->addMessage("success^ Leave application successfully applied");
				   return $this->redirect()->toRoute('leave', array('action' => 'leavedetail', 'id'=>$form['application_id']));
			}			
			else{
				  $this->flashMessenger()->addMessage("error^ Failed to apply leave application, Try again");
				  return $this->redirect()->toRoute('leave', array('action' => 'leavedetail', 'id'=>$form['application_id']));
			}		
		endif;
		return new ViewModel(array(
			'title' => 'Leave Detail',
			'leavedetail' => $this->getDefinedTable(Hr\LeaveTable::class)->get($this->_id),
			'leaveObj' => $this->getDefinedTable(Hr\LeaveTable::class),
			'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
			'usersObj' => $this->getDefinedTable(Administration\UsersTable::class),
			'leaveFlowObj' => $this->getDefinedTable(Hr\LeaveFlowTable::class),
			'userID'       => $this->_login_id,
			'leaveActionObj'  => $this->getDefinedTable(Hr\LeaveActionTable::class),
			'userRoleObj'  => $this->getDefinedTable(Acl\UserroleTable::class),
			'ActivityLogObj' => $this->getDefinedTable(Acl\ActivityLogTable::class),
			'casual_bal' 	 => $casual_bal,
			'earned_bal' 	 => $earned_bal,
			'total_leave'	 => $leave_taken,
			'total_bal'	 	 => $total_balance,
		));	
	}
	
	/**
	 *  process leave action
	 */
	public function processAction()
	{
		$this->init();		
		if($this->getRequest()->isPost()):		
			$form = $this->getRequest()->getPost()->toArray();
			$status = $this->getDefinedTable(Hr\LeaveActionTable::class)->getColumn($form['action'],'status');
			$description = $this->getDefinedTable(Hr\LeaveActionTable::class)->getColumn($form['action'],'description');
			$leave_update = array(
			                 'id'              => $form['application'],
							 'leave_official'  => $form['routing'],
							 'reporting_date'  => $form['reporting_date'],
							 'actual_leave_taken' => $form['actual_leave_taken'],
							 'remark_log'      => $form['remarks'],
							 'status' 		   => $status,
							 'author'          => $this->_author,
							 'modified'        => $this->_modified
			                );
			 //print_r($leave_update); exit; 
			$result = $this->getDefinedTable(Hr\LeaveTable::class)->save($leave_update);
			if($result){
			  	   $this->flashMessenger()->addMessage("success^ Leave ".$description);
				   return $this->redirect()->toRoute('leave', array('action' => 'leavedetail', 'id'=>$form['application']));
			}
			else{
				   $this->flashMessenger()->addMessage("error^ Cannot ". $description);
				   return $this->redirect()->toRoute('leave', array('action' => 'leavedetail', 'id'=>$form['application']));
			}
        endif; 		
		$viewModel =  new ViewModel(array(
			'title' => 'Leave Application',
			'users' => $this->getDefinedTable(Administration\UsersTable::class)->get(array('u.status'=>'1', 'u.role'=>array('4','5'))),
			'leaveTypes' => $this->getDefinedTable(Hr\LeaveTypeTable::class)->getAll(),
			'id'  => $this->_id,
			'leaveFlowObj' => $this->getDefinedTable(Hr\LeaveFlowTable::class),
			'userObj' => $this->getDefinedTable(Administration\UsersTable::class),
			'userRoleObj'  => $this->getDefinedTable(Acl\UserroleTable::class),
			'leaveObj' => $this->getDefinedTable(Hr\LeaveTable::class),
			'userID' => $this->_author,
		));	
		$viewModel->setTerminal('false');
        return $viewModel;		
	}
	
	public function encashprocessAction(){
	    $this->init();
		if($this->getRequest()->isPost()):		
			$form = $this->getRequest()->getPost()->toArray();		
			$status = $this->getDefinedTable(Hr\LeaveActionTable::class)->getColumn($form['action'],'status');
			$description = $this->getDefinedTable(Hr\LeaveActionTable::class)->getColumn($form['action'],'description');
			$encash_update = array(
			                 'id'              => $form['application'],
							 'leave_official'  => $form['routing'],
							 'remark_log'      => $form['remarks'],
							 'status' 		   => $status,
							 'author'          => $this->_author,
							 'modified'        => $this->_modified
			                );
							
			$result = $this->getDefinedTable(Hr\LeaveEncashTable::class)->save($encash_update);
			if($result){
			  	   $this->flashMessenger()->addMessage("success^ Leave ".$description);
				   return $this->redirect()->toRoute('leave', array('action' => 'encashmentdtl', 'id'=>$form['application']));
			}
			else{
				   $this->flashMessenger()->addMessage("error^ Cannot ". $description);
				   return $this->redirect()->toRoute('leave', array('action' => 'encashmentdtl', 'id'=>$form['application']));
			}
        endif; 		
		$viewModel = new ViewModel(array(			
			'title' => 'Leave Encashment Application',
			'users' => $this->getDefinedTable(Administration\UsersTable::class)->get(array('u.status'=>'1', 'u.role'=>array('4','5'))),
			'id'  => $this->_id,
			'leaveFlowObj' => $this->getDefinedTable(Hr\LeaveFlowTable::class),
			'userObj' => $this->getDefinedTable(Administration\UsersTable::class),
			'userRoleObj'  => $this->getDefinedTable(Acl\UserroleTable::class),
			'leaveObj' => $this->getDefinedTable(Hr\LeaveTable::class),
			'userID' => $this->_author,
		));
		$viewModel->setTerminal(True);
		return $viewModel;
	}
	
	/**
	 *edit leave detail
	 */
	public function editleaveAction(){
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost()->toArray();
			$authorEmpID = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author, 'employee');	
			$leaveOfficialResult = $this->getDefinedTable(Acl\UserroleTable::class)->get(array('user'=>$this->_author,'subrole'=>'17'));
			if($form['applicant'] != $authorEmpID && sizeof($leaveOfficialResult) > 0 ){	$leave_officier = $this->_author; }else{ $leave_officier = '0'; }		
			
			if($form['declaration'] == '1' && $form['applicant'] > 0 ):
			     $data = array(		
					'id'		   => $form['leaveID'],
					'employee'     => $form['applicant'],
					'leave_type'   => $form['leave_type'],
					'start_date'   => $form['start_date'],
					'end_date'     => $form['end_date'],
					'no_of_days'   => $form['no_of_days'],
					'contact'      => $form['contact'],
					'delegation'   => $form['delegation'],
					'sanction_order_no'  => $form['sanction_order_no'],
					'remarks'  			 => $form['remarks'],
					'leave_official'   => $leave_officier,
					'author'       => $this->_author,
					'modified'     => $this->_modified					
				);	
                $data = $this->_safedataObj->rteSafe($data);
			    $result = $this->getDefinedTable(Hr\LeaveTable::class)->save($data);	
				if($result > 0 ){
				   $this->flashMessenger()->addMessage("success^ Successfully Edited Leave application");
				   return $this->redirect()->toRoute('leave', array('action' => 'leavedetail', 'id'=>$form['leaveID']));
				}			
				else{
				  $this->flashMessenger()->addMessage("error^ Failed to Edit leave application, Try again");
				  return $this->redirect()->toRoute('leave', array('action' => 'editleave', 'id'=>$form['leaveID']));
				}				
			endif; 
		endif;
		$viewModel = new ViewModel(array(
			'title'=> 'Edit Leave Application',
		    'leavedetails' => $this->getDefinedTable(Hr\LeaveTable::class)->get($this->_id),
			'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
			'leavetypes' => $this->getDefinedTable(Hr\LeaveTypeTable::class)->getAll(),
			'userRoleObj'  => $this->getDefinedTable(Acl\UserroleTable::class),
			'userID'       => $this->_login_id,
			'login_emp_ID' => $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'employee'),
		));
		return $viewModel;
	}
	
	/**
	 * leave encashment
	*/
	public function encashmentAction(){
		$this->init();
		
		return new ViewModel(array(
			'title'=> 'Leave Encashment',
			'userID'  => $this->_login_id,
			'encashObj' => $this->getDefinedTable(Hr\LeaveEncashTable::class),
			'employeeObj'  => $this->getDefinedTable(Hr\EmployeeTable::class),
			'userObj'      => $this->getDefinedTable(Administration\UsersTable::class),
			'userRoleObj'  => $this->getDefinedTable(Acl\UserroleTable::class),
		));
	}
	
	/**
	 *leave encashment
	*/
	public function getsalarydtlAction(){
		$this->init();
		$form = $this->getRequest()->getPost();		
		$employeeID = $form['employee_id'];
		$salaryDtls = $this->getDefinedTable(Hr\PaystructureTable::class)->get(array('sd.employee' => $employeeID, 'pht.deduction' => 0));  
		$total_earning = 0;
		
		foreach($salaryDtls as $dtl):
		     $total_earning += $dtl['amount'];
		endforeach;
	
		$deductions = $this->getDefinedTable(Hr\PaystructureTable::class)->get(array('sd.employee' => $employeeID, 'sd.pay_head' => '11'));		
		foreach($deductions as $deduct):
		     $PIT_deduct  = $deduct['amount'];
		endforeach;
		
		echo json_encode(array(
			'gross_salary' => $total_earning,
			'PIT_deduct'   => $PIT_deduct
		));
		exit;
	}
	
	/**
	 *Employee Details
	*/
	public function displayempdtlAction(){
	   $this->init();
	   $employeeID = $this->_id;
	   $viewModel =  new ViewModel(array(
	        'empDtls' => $this->getDefinedTable(Hr\EmployeeTable::class)->get($employeeID),
	   ));
	   $viewModel->setTerminal('false');
       return $viewModel;		
	}
	/**
	 *Add leave encashment
	*/
	public function addencashmentAction(){
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost()->toArray();		
		    $encashedDate = $this->getDefinedTable(Hr\LeaveEncashTable::class)->getMax('encash_date', array('employee'=>$form['applicant'],'status'=>array(1,2,5,3,7)));
			if(strlen($encashedDate)>0){
				$today = date('Y-m-d');
				$timestamp_start = strtotime($encashedDate);
                $timestamp_end = strtotime($today);
				$difference = abs($timestamp_end - $timestamp_start);
				$years = floor($difference/(60*60*24*365));
				if($years < 1){
					$this->flashMessenger()->addMessage("notice^ Leave Encashment failed as you have applied on ".$encashedDate ." and you can only apply after one year");
				    return $this->redirect()->toRoute('leave', array('action' => 'encashment'));
				}
			}
						
			     $data = array(							
					'employee'           => $form['applicant'],
					'encash_date'        => $form['encash_date'],
					'no_of_encashed_days'=> $form['no_of_encashed_days'],
					'leave_balance'      => $form['leave_balance'],
					'leave_balance_date' => date('Y-m-d'),
					'payment_amount'     => $form['payment_amount'],
					'encash_sub_head'    => '',
					'deduction'   		 => $form['deduction'],
					'deduction_sub_head' => '',
					'status'             => '1',
					'remarks'  		     => $form['remarks'],
					'author'             => $this->_author,
					'created'            => $this->_created,
					'modified'           => $this->_modified					
				 );
				 
                $data = $this->_safedataObj->rteSafe($data);
			    $result = $this->getDefinedTable(Hr\LeaveEncashTable::class)->save($data);	
				if($result > 0 ){
				   $this->flashMessenger()->addMessage("success^ Successfully saved the Leave Encashment");
				   return $this->redirect()->toRoute('leave', array('action' => 'encashmentdtl', 'id'=>$result));
				}			
				else{
				  $this->flashMessenger()->addMessage("error^ Failed to apply leave Encashment, Try again");
				  return $this->redirect()->toRoute('leave', array('action' => 'addencashment'));
				}	
		endif;
		return new ViewModel(array(
			'title'=> 'Add Leave Encashment',
			'userID'       => $this->_login_id,
			'login_emp_ID' => $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'employee'),
			'employeeObj'  => $this->getDefinedTable(Hr\EmployeeTable::class),
			'leaveTypes'   => $this->getDefinedTable(Hr\LeaveTypeTable::class)->getAll(),
			'userRoleObj'  => $this->getDefinedTable(Acl\UserroleTable::class),
			'userObj'      => $this->getDefinedTable(Administration\UsersTable::class),
		));
	}
	
	public function encashmentdtlAction(){
		$this->init();		
        $params = !empty($this->_id) ? explode("-", $this->_id) : array();
		if(isset($params['1']) && isset($params['2']) && $params['1'] == '1' && $params['2'] > 0){
			$flag = $this->getDefinedTable(Acl\NotifyTable::class)->getColumn($params['2'], 'flag'); 
			if($flag == "0") {
				$notify = array('id' => $params['2'], 'flag'=>'1');
               	$this->getDefinedTable(Acl\NotifyTable::class)->save($notify); 	
			}				
		}			
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost()->toArray();				 
			  if($form['apply'] == '1') {  $status = "5"; $msg="Successfully Applied for Leave Encashment"; }
			  if($form['cancel'] == '2') {  $status = "4"; $msg = "Leave Encashment Cancelled"; }
			  if($form['approve'] == '3') {  $status = "7"; $msg = "Leave Encashment Approved"; }
			  if($form['approve'] == '4') {  $status = "9"; $msg = "Leave Encashment Rejected"; }
			  
			  if($form['apply'] == '1'){
				$subRoles = $this->getDefinedTable(Acl\UserroleTable::class)->get(array('subrole'=>array('17')));   
			    foreach($subRoles as $role):
				  echo"<pre>"; print_r($role);
				endforeach;
			  } exit; 
			  $data = array(	
			        'id'   		=> $form['application_id'], 
					'status'    => $status,
					'remarks'   => $form['remarks'],
       			    'author'    => $this->_author,
					'modified'  => $this->_modified					
			  );
				 
                $data = $this->_safedataObj->rteSafe($data);
			    $result = $this->getDefinedTable(Hr\LeaveEncashTable::class)->save($data);	
				if($result > 0 ){
				   $this->flashMessenger()->addMessage("success^".$msg);
				   return $this->redirect()->toRoute('leave', array('action' => 'encashmentdtl', 'id'=>$result));
				}			
				else{
				  $this->flashMessenger()->addMessage("error^ Failed to apply leave Encashment, Try again");
				  return $this->redirect()->toRoute('leave', array('action' => 'addencashment'));
				}	
        endif;			
		return new ViewModel(array(
			'title'        => 'Leave Encashment Details',
			'encash_id'    => $this->_id,
			'userID'       => $this->_login_id,	
			'encashDtls'   => $this->getDefinedTable(Hr\LeaveEncashTable::class)->get($this->_id),		
			'userRoleObj'  => $this->getDefinedTable(Acl\UserroleTable::class),
			'usersObj'     => $this->getDefinedTable(Administration\UsersTable::class),
			'employeeObj'  => $this->getDefinedTable(Hr\EmployeeTable::class),
			'leaveFlowObj' => $this->getDefinedTable(Hr\LeaveFlowTable::class),
			'leaveActionObj'  => $this->getDefinedTable(Hr\LeaveActionTable::class),
		));
	}
}

