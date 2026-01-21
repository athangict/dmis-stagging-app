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
class EmployeeController extends AbstractActionController
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
	public function getEmployee($emp_status)
	{		
		//$emp_id = $this->_user->employee;
	        $this->init();	 
		//$locations = explode(',',$this->_user->admin_location);
                $admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
                $admin_loc_array = explode(',',$admin_locs);
		//$employeelist = $this->getDefinedTable(Hr\EmployeeTable::class)->getAllEmployee($admin_loc_array ,$emp_status);//$emp_id, 
		$employeelist = $this->getDefinedTable(Hr\EmployeeTable::class)->getAll();//$emp_id, 
		return $employeelist;
	} 
	
	/**
	 *  index action
	 */
	public function indexAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$selected_status = $form['emp_status'];
			$employeelist = $this->getEmployee($selected_status);
		}else{
			$selected_status = '1';
			$employeelist = $this->getEmployee($selected_status);
		}
		
		if(sizeof($employeelist)==1):
$emp = null; // Initialize to avoid undefined variable warning
			foreach($employeelist as $row):
				$emp = $row;
			endforeach;
			$this->redirect()->toRoute('employee', array('action'=>view, 'id'=>$emp['id']));
		endif;
		
		return new ViewModel(array(
			'title' => 'Employee',
			'employee' => $employeelist,
			'selected_status' => $selected_status,	
			'employee_status' => $this->getDefinedTable(Hr\EmployeeStatusTable::class)->getAll(),
           'deptObj' =>$this->getDefinedTable(Administration\DepartmentTable::class),
			//'positionObj' =>$this->getDefinedTable(Hr\PositiontitleTable::class),
		));	
	}
	
	/**
	*	Add new Employee action
	**/
	public function addemployeeAction()
	{
		$this->init();
		if($this->_id > 0):
			return $this->redirect()->toRoute('employee', array('action' => 'view', 'id' => $this->_id));
		endif;
		if($this->getRequest()->isPost()):
			$data = array_merge_recursive(
				$this->getRequest()->getPost()->toArray(),
				$this->getRequest()->getFiles()->toArray()
			);		
			/*if(!$this->flashMessenger()->hasCurrentMessages()):				
    			$size = new Size(array('max'=>2000000));
    			$ext = new Extension('jpg, png, gif');    			
    			$adapter = new \Laminas\File\Transfer\Adapter\Http();
    			$adapter->setValidators(array($size, $ext), $data['imageupload']);    			
    			foreach ($adapter->getFileInfo() as $file => $info);
				$path = pathinfo($info['name']);
				if($path['filename']):				
					$a= rand(0,10);
					$b=chr(rand(97,122));
					$c=chr(rand(97,122));
					$d= rand(0,11000);					
					$ext = strtolower($path['extension']);
					$fileName =  md5($File['name'].$a.$b.$c.$d). '.' .$ext; //file path of the main picture
					$directory = $this->_dir."/employee/";
					$img = $info['tmp_name'];
					//resize image and upload				
					$imgWidth = 192;
					$imgHeight = 192;
					$im = imageCreateTrueColor($imgWidth, $imgHeight);					
					switch($ext):
					case 'jpg':
					case 'jpeg': $im_org = imagecreatefromjpeg($img);
						imageCopyResampled($im, $im_org, 0, 0, 0, 0, $imgWidth, $imgHeight, imageSX($im_org), imageSY($im_org));
						imageJpeg($im, $directory . $fileName, 100);
					break;					
					case 'png': $im_org = imagecreatefrompng($img);
						imageCopyResampled($im, $im_org, 0, 0, 0, 0, $imgWidth, $imgHeight, imageSX($im_org), imageSY($im_org));
						imagepng($im, $directory . $fileName, 100);
					break;					
					case 'gif': $im_org = imagecreatefromgif($img);
						imageCopyResampled($im, $im_org, 0, 0, 0, 0, $imgWidth, $imgHeight, imageSX($im_org), imageSY($im_org));
						imagegif($im, $directory . $fileName, 100);
					break;					
					default : 	$fileName = NULL;
					break;
					endswitch;
					//---------------------------------------endof image upload------------------------
					//change uploaded user photo permission
					if ( $handle = @opendir($directory) ):
						if( !@is_dir($directory . $fileName) ):
						chmod($directory . $fileName, 0777);
						endif;
					endif;					
					@closedir($handle);
				endif;
			endif;	*/		
			/*
			 * Generating the employee Id
			 *
			 * */ 
			$year = date('Y');
			$empid = $this->getDefinedTable(Hr\EmployeeTable::class)->getLastEmpId($year);			
			if($empid > 0):
				$newempid = $empid+1;
			else:
				$newempid = $year.'001';
			endif;
			/** Bank account and Cash **/
			if($data['cash'] == 1){
				$bank_account_no = '0';
				$bank_address = '';
			}else{
				$bank_account_no = $data['bank_account_no'];
				$bank_address = $data['bank_address'];
			}
			$data1=array(
				'emp_id' => $newempid,
				'full_name' => strtoupper($data['full_name']),
				'designation' => strtoupper($data['designation']),
				'gender' => $data['gender'],
				'cid' => $data['cid'],
				'dob' => $data['dob'],
				'mobile' => $data['mobile'],
				'email' => $data['email'],
				'nationality' => strtoupper($data['nationality']),
				'religion' => strtoupper($data['religion']),
				'dzongkhag'=>$data['dzongkhag'],
				'gewog'=>$data['gewog'],
				'village' => $data['village'],
				'house_no' => $data['house_no'],
				'thram_no' => $data['thram_no'],
				'bank_account_no' => $bank_account_no,
				'bank_address' => $bank_address,
				'height' => $data['height'],
				'blood_group' => $data['blood_group'],
				//'photo' => $fileName,
				'activity' => $data['activity'],
				'department' => $data['department'],
				'location' => $data['location'],
				'position_title' => $data['position_title'],
				'position_level' => $data['position_level'],
				'supervisor' => $data['supervisor'],
				'status' => $this->getDefinedTable(Hr\AppointmentTypeTable::class)->getColumn($data['appointment'],'status'),
				'type' => $data['type'],
				'increment_type' => $data['increment_type'],
				'author' => $this->_author,
				'created' => $this->_created,
				'modified' => $this->_modified,

				);
				//echo'<pre>';print_r($data1);exit;
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Hr\EmployeeTable::class)->save($data1);	
			if($result > 0):		
				$data2 = array(
					'employee' => $result,
					'employee_type' => $data['type'],
					'activity' => $data['activity'],
					'department' => $data['department'],
					'location' => $data['location'],
					'position_title' => $data['position_title'],
					'supervisor' => $data['supervisor'],
					'designation' => strtoupper($data['designation']),
					'position_level' => $data['position_level'],
					'type_of_appointment' => $data['appointment'],				
					'office_order_date' => $data['office_order_date'],
					'office_order_no' => $data['office_order_no'],
					'start_date' => $data['start_date'],
					'author' => $this->_author,
					'created' => $this->_created,
					'modified' => $this->_modified					
				);
				//echo'<pre>';print_r($data1);exit;
				$data2 = $this->_safedataObj->rteSafe($data2);
				$result2 = $this->getDefinedTable(Hr\EmpHistoryTable::class)->save($data2);
				if($result2 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ New employee(EID: ".$newempid.") successfully Added");
					return $this->redirect()->toRoute('employee', array('action' => 'view', 'id' => $result)); 
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					//remove uploaded user photo 
					if ( $handle = @opendir($directory) ):
						if( !@is_dir($directory . $fileName) ):
						@unlink($directory . $fileName);
						endif;
					endif;					
					@closedir($handle);
					$this->flashMessenger()->addMessage('Failed^ Failed to add new employee'); 
				endif;
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				//remove uploaded user photo 
				if ( $handle = @opendir($directory) ):
					if( !@is_dir($directory . $fileName) ):
					@unlink($directory . $fileName);
					endif;
				endif;					
				@closedir($handle);
				$this->flashMessenger()->addMessage('Failed^ Failed to add new employee'); 
			endif; 
		endif;		
		return new ViewModel(array(
			'title' => 'Add Employee',
			'departments' => $this->getDefinedTable(Administration\DepartmentTable::class)->getAll(),
			//'regions' => $this->_permissionObj->getregion(),
			'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
			'dzongkhags' => $this->getDefinedTable(Administration\DistrictTable::class)->getAll(),
			//'position_title' => $this->getDefinedTable(Hr\PositiontitleTable::class)->getAll(),
			'appoint' => $this->getDefinedTable(Hr\AppointmentTypeTable::class)->getAll(),
			'employees' => $this->getDefinedTable(Hr\EmployeeTable::class)->getAll(),
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'emptypes' => $this->getDefinedTable(Hr\EmployeeTypeTable::class)->getAll(),
			'increment_types' => $this->getDefinedTable(Hr\IncrementTypeTable::class)->getAll(),
			'postlevels' => $this->getDefinedTable(Hr\PositionlevelTable::class)->getAll(),
			'bank' => $this->getDefinedTable(Administration\BankTable::class)->getAll(),
		));   	
	} 
	
	/**
	 * get position title and pay scale 
	 */
	 public function getptitlepscaleAction()
	 {
		//$this->init();
		$form = $this->getRequest()->getpost();			
		$plevelID = $form['payscale_id'];
		
		$min	 = $this->getDefinedTable(Hr\PositionlevelTable::class)->getColumn($plevelID, 'min_pay');
		$incre	 = $this->getDefinedTable(Hr\PositionlevelTable::class)->getColumn($plevelID, 'increment');
		$max	 = $this->getDefinedTable(Hr\PositionlevelTable::class)->getColumn($plevelID, 'max_pay');
		$pay_scale = $min."-".$incre."-".$max;
		
		$position_titles = $this->getDefinedTable(Hr\PositiontitleTable::class)->get(array('position_level' => $plevelID));
		
		$pos_title ="<option value=''></option>";
		foreach($position_titles as $position_title):
			$pos_title .="<option value='".$position_title['id']."'>".$position_title['position_title']."</option>";
		endforeach;
		
		echo json_encode(array(
				'pay_scale' => $pay_scale,
				'pt' => $pos_title,
		));
		exit;
	 }
	/**
	 *View Employee action
	 **/
	public function viewAction()
	{
		$this->init();	
		$photo = $this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($this->_id, 'photo');
		if($photo !=""):
			$filename = $this->_dir."/employee/". $photo;
			$empimg = null;		
			if (file_exists($filename)) {
				$handle = fopen($filename, "rb");
				$empimg = fread($handle, filesize($filename));
				fclose($handle);
			}		
		endif;
		$payrollID = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('id', array('employee'=>$this->_id));
		$basicPays = $this->getDefinedTable(Hr\PaydetailTable::class)->get(array('pay_roll'=>$payrollID, 'pd.pay_head'=>'1'));
		foreach($basicPays as $basicPay):
			$basicPAY = $basicPay['amount'];
		endforeach;
		$location = $this->getDefinedTable(Hr\EmpHistoryTable::class)->getColumn(array('employee' => $this->_id), 'location');
		$region = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($location, 'region');		
		return new ViewModel(array(
			'title' => 'view',
			'employee' => $this->getDefinedTable(Hr\EmployeeTable::class)->get($this->_id),
			'emphistory' => $this->getDefinedTable(Hr\EmpHistoryTable::class)->getMaxRow('start_date', array('employee' => $this->_id)),
			'qualification' => $this->getDefinedTable(Hr\QualificationTable::class)->get(array('employee' => $this->_id)),
			'family' => $this->getDefinedTable(Hr\FamilyTable::class)->get(array('employee' => $this->_id)),
			'award' => $this->getDefinedTable(Hr\AwardTable::class)->get(array('employee' => $this->_id)),
			'empimg'  	=> $empimg,
			'trainings' => $this->getDefinedTable(Hr\TrainingTable::class)->get(array('employee' => $this->_id)),
			'emptypes' => $this->getDefinedTable(Hr\EmployeeTypeTable::class)->getAll(),
			//'basicPAY'	  => $basicPAY,TEMPORARY
			'q_levelsObj' => $this->getDefinedTable(Hr\QualificationLevelTable::class),
		));
	}	
	/**
	 *edit Employee action
	 **/
	public function editemployeeAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();		
			/** Bank account and Cash **/
			if($form['cash'] == 1){
				$bank_account_no = '0';
				$bank_address = '';
			}else{
				$bank_account_no = $form['bank_account_no'];
				$bank_address = $form['bank_address'];
			}
			$data=array(
			        'id' => $this->_id,
					'full_name' => strtoupper($form['full_name']),
					'designation' => strtoupper($form['designation']),
					'gender' => $form['gender'],
					'cid' => $form['cid'],
					'dob' => $form['dob'],
					'mobile' => $form['mobile'],
					'email' => $form['email'],
					'nationality' => strtoupper($form['nationality']),
					'religion' => strtoupper($form['religion']),
					'village' => $form['village'],
					'house_no' => $form['house_no'],
					'thram_no' => $form['thram_no'],
					'bank_account_no' => $bank_account_no,
					'bank_address' =>$bank_address,
					'height' => $form['height'],
					'blood_group' => $form['blood_group'],
					'department' => $form['department'],
					'activity' => $form['activity'],
					'location' => $form['location'],
					'supervisor' => $form['supervisor'],
					'type' => $form['type'],
					'increment_type' => $form['increment_type'],
					'position_title' => $form['position_title'],
					'position_level' => $form['position_level'],
					'status' => $this->getDefinedTable(Hr\AppointmentTypeTable::class)->getColumn($form['appointment'],'status'),
					'author' => $this->_author,
					'modified' => $this->_modified,
	
			);	
			
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Hr\EmployeeTable::class)->save($data);	
			if($result > 0):
				$data1=array(
						'id' => $form['his_id'],
						'employee' => $this->_id,
						'employee_type' => $form['type'],
						'activity' => $form['activity'],
						'department' => $form['department'],
						'location' => $form['location'],
						'supervisor' => $form['supervisor'],
						'designation' => strtoupper($form['designation']),
						'position_title' => $form['position_title'],
						'position_level' => $form['position_level'],					
						'office_order_date' => $form['office_order_date'],
						'office_order_no' => $form['office_order_no'],
						'start_date' => $form['start_date'],
						'author' => $this->_author,
						'modified' => $this->_modified,						
				);
				$data1 = $this->_safedataObj->rteSafe($data1);
				$result1 = $this->getDefinedTable(Hr\EmpHistoryTable::class)->save($data1);
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ Employee details successfully Updated");
					return $this->redirect()->toRoute('employee', array('action' => 'editemployee', 'id' => $this->_id));
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to update employee detail");
				endif;
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to update employee detail");
			endif;
			return $this->redirect()->toRoute('employee', array('action' => 'view', 'id' => $this->_id));
		}
		
		return new ViewModel(array(
				'title' => 'Edit Employee',
				'departments' => $this->getDefinedTable(Administration\DepartmentTable::class)->getAll(),
    		    'employee' => $this->getDefinedTable(Hr\EmployeeTable::class)->get($this->_id),
		        'position_titleObj' => $this->getDefinedTable(Hr\PositiontitleTable::class),		    
		        'dzongkhags' => $this->getDefinedTable(Administration\DistrictTable::class)->getAll(),
		        'gewogs' => $this->getDefinedTable(Administration\BlockTable::class)->getAll(),
		        'villages' => $this->getDefinedTable(Administration\VillageTable::class)->getAll(),
		        'regions' => $this->_permissionObj->getregion(),
		        'permissionObj' => $this->_permissionObj,
				'latest_info' => $this->getDefinedTable(Hr\EmpHistoryTable::class)->getMaxRow('start_date', array('eh.employee'=>$this->_id)),
				'employees' => $this->getDefinedTable(Hr\EmployeeTable::class)->getAllEmployee(),
				'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'emptypes' => $this->getDefinedTable(Hr\EmployeeTypeTable::class)->getAll(),
				'appoint' => $this->getDefinedTable(Hr\AppointmentTypeTable::class)->getAll(),
				'increment_types' => $this->getDefinedTable(Hr\IncrementTypeTable::class)->getAll(),
				'postlevels' => $this->getDefinedTable(Hr\PositionlevelTable::class)->getAll(),
				'bank' => $this->getDefinedTable(Administration\BankTable::class)->getAll(),
		));
	}
	
	/**
	 *  changephoto Action --to Change user photo
	 **/
	public function changephotoAction()
	{
		$this->init();		 
		if (!isset($this->_id)):
            return $this->redirect()->toRoute('employee');
		else:	
    		$request = $this->getRequest();    	    
    		if ($request->isPost()):
                $data = array_merge_recursive(
    				$request->getPost()->toArray(),
    				$request->getFiles()->toArray()
    		    );    		
	    		if(!$this->flashMessenger()->hasCurrentMessages()):	    	
	        		$size = new Size(array('max'=>2000000));
	        		$ext = new Extension('jpeg, jpg, png, gif');	        			
	        		$adapter = new \Laminas\File\Transfer\Adapter\Http();
	        		$adapter->setValidators(array($size, $ext), $data['imageupload']);	        			
	        		foreach ($adapter->getFileInfo() as $file => $info):
	                    $path = pathinfo($info['name']);
	            		if($path['filename']):	            	
	                		$a= rand(0,10);
	                		$b=chr(rand(97,122));
	                		$c=chr(rand(97,122));
	                		$d= rand(0,11000);	                			
	                		$ext = strtolower($path['extension']);
	                		$fileName =  md5($File['name'].$a.$b.$c.$d). '.' .$ext; //file path of the main picture
							$directory = $this->_dir."/employee/";
	                		//resizing and uploading new image
	                		$img = $info['tmp_name'];	                	
	                		$imgWidth = 192;
	                		$imgHeight = 192;
	                		$im = imageCreateTrueColor($imgWidth, $imgHeight);	                	
	                		switch($ext):
	                    		case 'jpg':
	                    		case 'jpeg': $im_org = imagecreatefromjpeg($img);
	                        		imageCopyResampled($im, $im_org, 0, 0, 0, 0, $imgWidth, $imgHeight, imageSX($im_org), imageSY($im_org));
	                        		imageJpeg($im, $directory . $fileName, 100);
	                    		break;	                    	
	                    		case 'png': $im_org = imagecreatefrompng($img);
	                        		imageCopyResampled($im, $im_org, 0, 0, 0, 0, $imgWidth, $imgHeight, imageSX($im_org), imageSY($im_org));
	                        		imagepng($im, $directory . $fileName, 100);
	                    		break;	                    	
	                    		case 'gif': $im_org = imagecreatefromgif($img);
	                        		imageCopyResampled($im, $im_org, 0, 0, 0, 0, $imgWidth, $imgHeight, imageSX($im_org), imageSY($im_org));
	                        		imagegif($im, $directory . $fileName, 100);
	                    		break;	                    	
	                    		default : 	$fileName = NULL;
	                    		break;
	                		endswitch;
	                		if ( $handle = @opendir($directory) ):
	                    		if( !@is_dir($directory . $fileName) ):
	                    		  chmod($directory . $fileName, 0777);
	                    		endif;
	                        endif;
	            			@closedir($handle);
	            		endif;
    				endforeach;
	        		$prev_photo = $this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($this->_id, $column="photo");
	        		$data = array(
	        				'id'  		 => $this->_id,
	        				'photo'      => $fileName,
	        				'created'    => $this->_created,
	        				'modified'   => $this->_modified
	        		);
        		
	        		if($adapter->isValid()):
	        			$data = $this->_safedataObj->rteSafe($data);
	                    $result = $this->getDefinedTable(Hr\EmployeeTable::class)->save($data);
	    	
		        		if($result > 0):
		                    $this->flashMessenger()->addMessage("success^ User photo successfully changed");
		        		    //delete previous photo
		        		    if($prev_photo!='avatar.jpg'):
								if ( $handle = @opendir($directory) ):
									if( !@is_dir($directory . $prev_photo) ):
										@unlink($directory . $prev_photo);
									endif;
								endif;
								@closedir($handle);
							endif;
			    		    return $this->redirect()->toRoute('employee', array('action' => 'view', 'id'=>$this->_id));  
		        		else:
			        		// when user couldnot be added into database
			        		$this->flashMessenger()->addMessage("error^ Someting went wrong and photo couldnot be updated");
        			
				    		//deleted uploaded photo
				    		if ( $handle = @opendir($directory) ):
					    		if( !@is_dir($directory . $fileName) ):
					    			@unlink($directory . $fileName);
					    		endif;
				    		endif;
				    		@closedir($handle);
				    	endif;
    				else:
			    		// when user photo couldnot be added/uploaded
			    		foreach($adapter->getMessages() as $sms):
			                $fmessage ='error^'.$sms;
			    		endforeach;
                		$this->flashMessenger()->addMessage($fmessage);
					endif;
				endif;
				return $this->redirect()->toRoute('employee', array('action'=>'changephoto', 'id'=>$this->_id));
			else:
				$employees = $this->getDefinedTable(Hr\EmployeeTable::class)->get($this->_id);
	
				if(empty($employees) ):
					$this->flashMessenger()->addMessage("error^ Cannot find a employee with Id: ". $this->_id);
					return $this->redirect()->toRoute('employee', array('action' => 'employee'));
				endif;
	
				$ViewModel = new ViewModel(array(
					'title'    	=> 'Change Photo',
					'rowsets'   => $this->getDefinedTable(Hr\EmployeeTable::class)->get($this->_id),
					//'empimg'  	=> $empimg,
				));		
				$ViewModel->setTerminal(True);
				return $ViewModel;
			endif;
		endif;
	}
	
	
    /**
	 *Add new Employee qualification action
	 **/
	public function addqualificationAction()
	{
		$this->init();		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest();
			$data=array(
					'employee' => $this->_id,
					'qualification' => $form->getPost('qualification'),
					'course' => $form->getPost('course'),
					'institute' => $form->getPost('institute'),
					'location' => $form->getPost('location'),
					'completion_year' => $form->getPost('completion_year'),
					'author' => $this->_author,
					'created' => $this->_created,
					'modified' => $this->_modified,
	
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\QualificationTable::class)->save($data);	
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New qualification successfully Added");
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to add new qualification");
			endif;
			return $this->redirect()->toRoute('employee', array('action' => 'viewqualification', 'id' => $this->_id));
		}	
		$ViewModel = new ViewModel(array(
		        'id' => $this->_id,
				'title' => 'Add Employee Qualification',
                'employees' => $this->getDefinedTable(Hr\EmployeeTable::class)->getAll(),
				'q_levels' => $this->getDefinedTable(Hr\QualificationLevelTable::class)->getAll(),
		));

		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 *View qualification action
	 **/
	public function viewqualificationAction()
	{
		$this->init();	
		if($this->_id <= 0):
            $this->flashmessenger()->addMessage('notice^ Add employee details first');
            return $this->redirect()->toRoute('employee', array('action' => 'addemployee'));
		endif;
		
		return new ViewModel(array(
				'title' => 'view',
				'id' => $this->_id,
				'employee' => $this->getDefinedTable(Hr\EmployeeTable::class)->get($this->_id),
				'qualification' => $this->getDefinedTable(Hr\QualificationTable::class)->get(array('employee' => $this->_id)),
				'q_levelsObj' => $this->getDefinedTable(Hr\QualificationLevelTable::class),
		));
	}
	
	/**
	 *Edit Employee qualification action
	 **/
	public function editqualificationAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data=array(
			        'id' => $this->_id,
					'employee' => $form['employee'],
					'qualification' => $form['qualification'],
					'course' => $form['course'],
					'institute' => $form['institute'],
					'location' => $form['location'],
					'completion_year' => $form['completion_year'],
					'author' => $this->_author,
					'created' => $this->_created,
					'modified' => $this->_modified,
	
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\QualificationTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Qualification successfully Updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to Update qualification");
			endif;
			return $this->redirect()->toRoute('employee', array('action' => 'viewqualification', 'id' => $form['employee']));
		}
	
		
		$ViewModel = new ViewModel(array(
				'id' => $this->_id,
				'title' => 'Edit Employee Qualification',
				'qualification' => $this->getDefinedTable(Hr\QualificationTable::class)->get($this->_id),
				'q_levels' => $this->getDefinedTable(Hr\QualificationLevelTable::class)->getAll(),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	
	/**
	 *Add family action
	 **/
	public function addfamilyAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest();
			$data=array(
					'employee' => $this->_id,
			        'name' => $form->getPost('name'),
					'relation' => $form->getPost('relation'),
					'nationality' => $form->getPost('nationality'),
					'occupation' => $form->getPost('occupation'),
					'address' => $form->getPost('address'),
					'remarks' => $form->getPost('remarks'),
					'author' => $this->_author,
					'created' => $this->_created,
					'modified' => $this->_modified,
	
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\FamilyTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ New family details successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add family details");
	       	endif;
			return $this->redirect()->toRoute('employee', array('action' => 'viewfamily', 'id' => $this->_id));
		}
	
		$ViewModel = new ViewModel(array(
				'title' => 'Add Employee Family details',
		        'id' => $this->_id,
    		    'employees' => $this->getDefinedTable(Hr\EmployeeTable::class)->getAll(),
		));

		$ViewModel->setTerminal(True);
		return $ViewModel;
	}	
	

	/**
	 *View family action
	 **/
	public function viewfamilyAction()
	{
		$this->init();

		if($this->_id <= 0):
		$this->flashmessenger()->addMessage('notice^ Add employee details first');
		return $this->redirect()->toRoute('employee', array('action' => 'addemployee'));
		endif;
		
		return new ViewModel(array(
				'title' => 'view',
				'id' => $this->_id,
				'employee' => $this->getDefinedTable(Hr\EmployeeTable::class)->get($this->_id),
		        'family' => $this->getDefinedTable(Hr\FamilyTable::class)->get(array('employee' => $this->_id)),
		));
	}
	
	/**
	 *Edit family action
	 **/
	public function editfamilyAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data=array(
			        'id' => $this->_id,
					'employee' => $form['employee'],
					'name' => $form['name'],
					'relation' => $form['relation'],
					'nationality' => $form['nationality'],
					'occupation' => $form['occupation'],
					'address' => $form['address'],
					'remarks' => $form['remarks'],
					'author' => $this->_author,
					'created' => $this->_created,
					'modified' => $this->_modified,
	
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\FamilyTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Family detail successfully Updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to Update family details");
			endif;
			return $this->redirect()->toRoute('employee', array('action' => 'viewfamily', 'id' => $form['employee']));
		}
		$ViewModel = new ViewModel(array(
				'id' => $this->_id,
				'title' => 'Edit Employee Family',
				'family' => $this->getDefinedTable(Hr\FamilyTable::class)->get($this->_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 *Add Award action
	 **/
	public function addawardAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest();
			$data=array(
					'employee' => $this->_id,
					'particular' => $form->getPost('particular'),
					'award' => $form->getPost('award'),
					'award_date' => $form->getPost('award_date'),
					'authority' => $form->getPost('authority'),
					'author' => $this->_author,
					'created' => $this->_created,
					'modified' => $this->_modified,
	
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\AwardTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ New award successfully Added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add new award");
			endif;
			return $this->redirect()->toRoute('employee', array('action' => 'viewaward', 'id' => $this->_id));
		}
	
		$ViewModel = new ViewModel(array(
				'title' => 'Add Employee Award details',
				'id' => $this->_id,
		));

		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 *View award action
	 **/
	public function viewawardAction()
	{
	    $this->init();	    
	    if($this->_id <= 0):
			$this->flashmessenger()->addMessage('notice^ Add employee detail first');
			return $this->redirect()->toRoute('employee', array('action' => 'addemployee'));
	    endif;
	
		return new ViewModel(array(
				'title' => 'view',
				'employee' => $this->getDefinedTable(Hr\EmployeeTable::class)->get($this->_id),
				'award' => $this->getDefinedTable(Hr\AwardTable::class)->get(array('employee' => $this->_id)),
		));
	}
	
	
	/**
	 *Edit Award action
	 **/
	public function editawardAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data=array(
			        'id' => $this->_id,
					'employee' => $form['employee'],
					'particular' => $form['particular'],
					'award' => $form['award'],
					'award_date' => $form['award_date'],
					'authority' => $form['authority'],
					'author' => $this->_author,
					'modified' => $this->_modified,
	
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\AwardTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Award successfully Updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to Update award");
			endif;
			return $this->redirect()->toRoute('employee', array('action' => 'viewaward', 'id' => $form['employee']));
		}
		
		$ViewModel = new ViewModel(array(
				'title' => 'Edit Employee Award details',
				'id' => $this->_id,
		        'award' => $this->getDefinedTable(Hr\AwardTable::class)->get($this->_id),
		));
		$ViewModel->setTerminal(true);
		return $ViewModel;		
	}
     
     /**
      * add user Action
      */
     public function adduserAction()
     {
     	$this->init();
     	
		foreach($this->getDefinedTable(Hr\EmployeeTable::class)->get($this->_id) as $erow);
		
		$staticSalt = $this->_config['static_salt'];
     	$dynamicSalt = $this->generateDynamicSalt();
     	$staticSalt = $this->getStaticSalt();
     	$generatedPassword = $this->generatePassword();		
     	$password = $this->encryptPassword(
     			$staticSalt,
     			$generatedPassword,
     			$dynamicSalt
     	);
     	
     	//foreach($this->getDefinedTable(Hr\EmployeeTable::class)->get($this->_id) as $erow);
     	$data1 = array(
     			'username' => substr(str_replace(' ','',strtolower($erow['full_name'])),0,3).'_'.$erow['emp_id'],
     			'password' => $password,
     			'salt'     => $dynamicSalt,
     			'name'	   => $erow['full_name'],
     			'email'	   => $erow['email'],
     			'role'	   => 2,
     			'location' => $erow['location_id'],
     			'department'=> $erow['department_id'],
     			'mobile'   => $erow['mobile'],
     			'status'   => 1,
     			'credit_authority' => 0, 
     			'photo'	   => $erow['photo'],
     			'employee'	   => $erow['id'],
     			'author'   => $this->_author,
     			'created'  => $this->_created,
     			'modified' => $this->_modified,
     	);
     	$this->_connection->beginTransaction(); //***Transaction begins here***//
     	$result = $this->getDefinedTable(Administration\UsersTable::class)->save($data1);
     	if($result > 0):
			$data2 = array(
					'id'            => $this->_id,
					'system_user'   => 1,
					'author'	    => $this->_author,
					'modified'      => $this->_modified,
			);
			$result1 = $this->getDefinedTable(Hr\EmployeeTable::class)->save($data2);
			
			$userDtls = $this->getDefinedTable(Administration\UsersTable::class)->get($result);
			foreach($userDtls as $dtl):				
				$name = $dtl['name']; $mobile = $dtl['mobile']; $email = $dtl['email'];				  
			endforeach; 
			$this->sendFCBLEmail($name, $mobile, $email, $generatedPassword); 

			if($result1 > 0):
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Successfully added for system login");
				$source = $this->_dir."/employee/".$erow['photo'];
                                /** Upload Path and Details **/
				$fileManagerDir2 = $this->_config['file_manager']['public_dir'];
				$this->_dir2 =realpath($fileManagerDir2);

				$destination = $this->_dir."/user/".$erow['photo'];
				if($source!="avatar.jpg"):
					if(copy($source, $destination)):
						chmod($destination.$erow['photo'], 0777);
					endif;
				endif;
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessager()->addMessage("error^ Couldn't allow system login");
			endif;
		else:
			$this->_connection->rollback(); // rollback transaction over failure
			$this->flashMessager()->addMessage("error^ Couldn't allow system login");
		endif;
     	return $this->redirect()->toRoute('employee');
     	 
     }

	 private function sendFCBLEmail($name, $mobile, $email, $password) {		
		$view       = new \Laminas\View\Renderer\PhpRenderer();
		$resolver   = new \Laminas\View\Resolver\TemplateMapResolver();
		$resolver->setMap(array(
			'mailTemplate' => __DIR__ . '/../../../view/hr/setpassword.phtml'
		));

		$view->setResolver($resolver);	 
		$viewModel  = new ViewModel();
		$viewModel->setTemplate('mailTemplate')->setVariables(array(
			'mobile'  => $mobile,
			'email'     => $email,	
			'password'  => $password,
			'name'      => $name
		));
	 
		$bodyPart = new \Laminas\Mime\Message();
		$bodyMessage    = new \Laminas\Mime\Part($view->render($viewModel));
		$bodyMessage->type = 'text/html';
		$bodyPart->setParts(array($bodyMessage));
	 
		$message = new \Laminas\Mail\Message();
		$message->addFrom('noreply@gmail.com', 'FCBL ERP Team')
				->addTo($email)
				->setSubject('FCBL ERP User Credentails')
				->setBody($bodyPart)
				->setEncoding('UTF-8');
		$transport  = new \Laminas\Mail\Transport\Sendmail();
		$transport->send($message);
	}
     
    ########## used for adduser action ################################
    public function generateDynamicSalt()
	{
		$dynamicSalt = '';
		for ($i = 0; $i < 20; $i++) {
			$dynamicSalt .= chr(rand(33, 126));
		}
		return $dynamicSalt;
	}
	
	public function generatePassword()
	{
		$password = chr(rand(97, 122));
		$password .= chr(rand(64, 90));
		$password .= chr(rand(48, 57));
		$password .= chr(rand(35, 38));
		$password .= chr(rand(97, 122));
		$password .= chr(rand(35, 38));
		$password .= chr(rand(48, 57));
		$password .= chr(rand(64, 90));
		return $password;
	}
	
	public function getStaticSalt()
	{
		$staticSalt = '';
		$config = $this->getServiceLocator()->get('Config');
		$staticSalt = $config['static_salt'];
		return $staticSalt;
	}
	
	public function encryptPassword($staticSalt, $password, $dynamicSalt)
	{
		return $password = SHA1($staticSalt . $password . $dynamicSalt);
	}

    ########################################################################
    
    /**
	 *  history action
	 */
	public function historyAction()
	{
		$this->init();
		if($this->_id <= 0):
			$this->flashmessenger()->addMessage('notice^ Add employee detail first');
			return $this->redirect()->toRoute('employee', array('action' => 'addemployee'));
	    endif;
		return new ViewModel(array(
				'title' => 'Employee History',
				'emphistory' => $this->getDefinedTable(Hr\EmpHistoryTable::class)->get(array('eh.employee'=>$this->_id)),
				'employee'   => $this->getDefinedTable(Hr\EmployeeTable::class)->get($this->_id),
				'employeeObj'   => $this->getDefinedTable(Hr\EmployeeTable::class),
		));
	}
	/**
	 * addemphistory action
	 */
	public function addhistoryAction()
	{
		$this->init();
		if(!isset($this->_id) || $this->_id == 0):
			$this->redirect()->toRoute('employee');
		endif;
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();	
            if(!isset($this->_id) || $this->_id == 0):
               $this->redirect()->toRoute('employee');
			endif; 	
			$data = array(	
					'employee' => $this->_id,
					'employee_type' => $form['type'],
					'activity'=>$form['activity'],
					'supervisor'=>$form['supervisor'],
					'department'=>$form['department'],
					'designation'=>strtoupper($form['designation']),
					'location'=>$form['location'],
					'position_title' => $form['post_title'],
					'type_of_appointment' => $form['appointment'],
					'position_level' => $form['post_level'],				
					'start_date' => $form['start_date'],
					//'end_date' => $form['end_date'],
					'office_order_no' => $form['office_order_no'],
					'office_order_date' => $form['office_order_date'],
					'reason' => $form['remark'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);		
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Hr\EmpHistoryTable::class)->save($data);				
			if($result > 0):			
				$latestHistory = $this->getDefinedTable(Hr\EmpHistoryTable::class)->getMaxRow('start_date', array('eh.employee'=>$this->_id));
				$result1 = 1;
				if($latestHistory[0]['id'] == $result):
					$emp_data = array(
							'id' => $this->_id,
							'designation' => strtoupper($form['designation']),
							'activity' => $form['activity'],
							'department' => $form['department'],
							'location' => $form['location'],
							'supervisor' => $form['supervisor'],
							'position_title' => $form['post_title'],
							'position_level' => $form['post_level'],
							'type' => $form['type'],
							'status' => $this->getDefinedTable(Hr\AppointmentTypeTable::class)->getColumn($form['appointment'],'status'),
							'author' =>$this->_author,
							'modified' =>$this->_modified,
					);				
					$emp_data = $this->_safedataObj->rteSafe($emp_data);
					$result1 = $this->getDefinedTable(Hr\EmployeeTable::class)->save($emp_data);
				endif;
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ New Employee History added");
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("Failed^ Failed to add new Employee History");
				endif;
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("Failed^ Failed to add new Employee History");
			endif;
			return $this->redirect()->toRoute('employee', array('action'=>'history', 'id'=>$this->_id));			 
		}
		return new ViewModel(array(
			'title' => 'New History',
			'emp_id'   => $this->_id,
			'dept' => $this->getDefinedTable(Administration\DepartmentTable::class)->getAll(),
			'appoint' => $this->getDefinedTable(Hr\AppointmentTypeTable::class)->getAll(),
			'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
			'position_titleObj' => $this->getDefinedTable(Hr\PositiontitleTable::class),		    	
			'employees' => $this->getDefinedTable(Hr\EmployeeTable::class)->getAllEmployee(),
			'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'emptypes' => $this->getDefinedTable(Hr\EmployeeTypeTable::class)->getAll(),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'latest_info' => $this->getDefinedTable(Hr\EmpHistoryTable::class)->getMaxRow('start_date', array('eh.employee'=>$this->_id)),
			'postlevels' => $this->getDefinedTable(Hr\PositionlevelTable::class)->getAll(),
		));	
	}
	
	/**
     * edit emphistory Action
     **/
    public function edithistoryAction()
    {
        $this->init();
        if($this->getRequest()->isPost())
        {
            $form=$this->getRequest()->getPost();
            $data=array(
                    'id' => $this->_id,
                    'employee_type' => $form['type'],
                    'activity'=>$form['activity'],
                    'department'=>$form['department'],
					'designation'=>strtoupper($form['designation']),
            		'location' => $form['location'],
					'supervisor'=>$form['supervisor'],
                    'position_title' => $form['post_title'],
                    'type_of_appointment' => $form['appointment'],
                    'position_level' => $form['post_level'],                 
                    'start_date' => $form['start_date'],
                    //'end_date' => $form['end_date'],
                    'office_order_no' => $form['office_order_no'],
            		'office_order_date' => $form['office_order_date'],
                    'reason' => $form['remark'],
                    'modified' =>$this->_modified,
            );
            $data = $this->_safedataObj->rteSafe($data);
            $this->_connection->beginTransaction(); //***Transaction begins here***//
            $result = $this->getDefinedTable(Hr\EmpHistoryTable::class)->save($data);
            if($result > 0):
				$result1 =1;//initial asignment
            	$emp_id = $this->getDefinedTable(Hr\EmpHistoryTable::class)->getColumn($result, 'employee');
	            $latestHistory = $this->getDefinedTable(Hr\EmpHistoryTable::class)->getMaxRow('start_date', array('eh.employee'=>$emp_id));
	            if($latestHistory[0]['id'] == $result):
		            $emp_data = array(
		            		'id' => $emp_id,
		            		'activity' => $form['activity'],
		            		'department' => $form['department'],
							'designation'=>strtoupper($form['designation']),
		            		'location' => $form['location'],
		            		'supervisor' => $form['supervisor'],
		            		'position_title' => $form['post_title'],
		            		'position_level' => $form['post_level'],
		            		'type' => $form['type'],
		            		'status' => $this->getDefinedTable(Hr\AppointmentTypeTable::class)->getColumn($form['appointment'],'status'),
		            		'author' =>$this->_author,
		            		'modified' =>$this->_modified,
		            );
            
            		$emp_data = $this->_safedataObj->rteSafe($emp_data);
            		$result1 = $this->getDefinedTable(Hr\EmployeeTable::class)->save($emp_data);
            	endif;
            	if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ Employee History successfully updated");
            	else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("Failed^ Failed to edit Employee History");
            	endif;
            else:
				$this->_connection->rollback(); // rollback transaction over failure
                $this->flashMessenger()->addMessage("Failed^ Failed to edit Employee History");
            endif;
            return $this->redirect()->toRoute('employee',array('action'=>'history', 'id'=>$form['employee']));
        } 
        if(!isset($this->_id) || $this->_id == 0):
			$this->redirect()->toRoute('employee');
		endif;  
        return new ViewModel(array(
            'emphistory' => $this->getDefinedTable(Hr\EmpHistoryTable::class)->get($this->_id),            
			'employees' => $this->getDefinedTable(Hr\EmployeeTable::class)->getAll(),
			'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
            'dept' => $this->getDefinedTable(Administration\DepartmentTable::class)->getAll(),
            'position_titleObj' => $this->getDefinedTable(Hr\PositiontitleTable::class),
            'appoint' => $this->getDefinedTable(Hr\AppointmentTypeTable::class)->getAll(),
            'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
            'emptypes' => $this->getDefinedTable(Hr\EmployeeTypeTable::class)->getAll(),
		    'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
		    'postlevels' => $this->getDefinedTable(Hr\PositionlevelTable::class)->getAll(),
        ));          
    }
    
    /**
	 *  leave action
	 */
	 
	public function leaveAction()
	{
		$this->init();
		
		$emp_initial_app_his = $this->getDefinedTable(Hr\EmpHistoryTable::class)->getColumn(array('employee' => $this->_id,'type_of_appointment' => '1'),'id');
		
		if($emp_initial_app_his <= 0):
			$this->flashmessenger()->addMessage('notice^ Add employee detail first');
			return $this->redirect()->toRoute('employee', array('action' => 'addemployee'));
	    endif;	
				
		$leaveDate = $this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($this->_id, 'leave_balance_date');
		$leaveBal = $this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($this->_id, 'leave_balance');
		
		if($leaveDate > 0 || $leaveBal > 0):
			$startDate = $leaveDate;
			$Balance = $leaveBal;
		else:
			$startDate = $this->getDefinedTable(Hr\EmpHistoryTable::class)->getColumn($emp_initial_app_his, 'start_date');
			$Balance = 0;
		endif; 
		// start date for view purpose only
		$minViewDate = $this->getDefinedTable(Hr\EmpHistoryTable::class)->getColumn($emp_initial_app_his, 'start_date');
			
		return new ViewModel(array(
				'title' => 'Leave',
				'startDate'=> $startDate,
				'Balance'  => $Balance,
				'minViewDate' => $minViewDate,
				'leaveObj' => $this->getDefinedTable(Hr\LeaveTable::class),
				'ltypeObj' => $this->getDefinedTable(Hr\LeaveTypeTable::class),
				'employee' => $this->getDefinedTable(Hr\EmployeeTable::class)->get($this->_id),			
				'leaveactionObj' => $this->getDefinedTable(Hr\LeaveActionTable::class),
				'userRoleObj'  => $this->getDefinedTable(Acl\RoleTable::class),
			    'userID'       => $this->_login_id,
		));		
	}
	
	
	/**
	 * addleave action
	 */
	public function addleaveAction()
	{
		$this->init();
		if(!isset($this->_id) || $this->_id == 0):
			$this->redirect()->toRoute('employee');
		endif;
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'employee' => $this->_id,
					'leave_type'=>$form['leave_type'],
					'no_of_days'=>$form['no_of_days'],
					'start_date'=>$form['start_date'],
					'end_date'=>$form['end_date'],
					'sanction_order_no'=>$form['sanction_order_no'],
					'remarks'=>$form['remarks'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\LeaveDetailTable::class)->save($data);
	
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New Leave Record successfully added");
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to add new Leave Record");
			endif;
			return $this->redirect()->toRoute('employee',array('action' => 'leave', 'id' => $this->_id));
		}
		$ViewModel = new ViewModel(array(
				'title' => 'Leave Details',
				'leavetype' => $this->getDefinedTable(Hr\LeaveTypeTable::class)->getAll(),
				'emp' => $this->getDefinedTable(Hr\EmployeeTable::class)->get($this->_id),
				'leavedetail'=>$this->getDefinedTable(Hr\LeaveDetailTable::class)->getAll(),
		));
		$ViewModel->setTerminal(true);
		return $ViewModel;
	}
	
	/**
	 * edit leave detail Action
	 **/
	public function editleaveAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest()->getPost();
			$data=array(
					'id' => $this->_id,
					'leave_type'=>$form['leave_type'],
					'no_of_days'=>$form['no_of_days'],
					'start_date'=>$form['start_date'],
					'end_date'=>$form['end_date'],
					'sanction_order_no'=>$form['sanction_order_no'],
					'remarks'=>$form['remarks'],
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\LeaveDetailTable::class)->save($data);
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Leave Record successfully updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to edit Leave Record");
			endif;
			return $this->redirect()->toRoute('employee',array('action' => 'leave', 'id' => $form['employee']));
		}
		if(!isset($this->_id) || $this->_id == 0):
			$this->redirect()->toRoute('employee');
		endif;
		$ViewModel = new ViewModel(array(
				'leavedetail' => $this->getDefinedTable(Hr\LeaveDetailTable::class)->get($this->_id),
				'leavetype' => $this->getDefinedTable(Hr\LeaveTypeTable::class)->getAll(),
				'emp' => $this->getDefinedTable(Hr\EmployeeTable::class)->getAll(),
		));
		$ViewModel->setTerminal(true);
		return $ViewModel;
	}
	
	/**
	 *  training action
	 */
	public function trainingAction()
	{
		$this->init();
		if(!isset($this->_id) || $this->_id == 0):
			$this->redirect()->toRoute('employee');
		endif;
		return new ViewModel(array(
				'title' => 'Training',
				'training' => $this->getDefinedTable(Hr\TrainingTable::class)->get(array('employee'=>$this->_id)),
				'employee' =>$this->getDefinedTable(Hr\EmployeeTable::class)->get($this->_id),	
				'id' => $this->_id,
				
		));
		
		
	}
	
	/**
	*Add new Training action
	**/
	public function addtrainingAction()
	{
		$this->init();
		if(!isset($this->_id) || $this->_id == 0):
			$this->redirect()->toRoute('employee');
		endif;
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data=array(
				'employee' => $this->_id,
				'training_name' => $form['training_name'],
				'start_date' => $form['start_date'],
				'end_date' => $form['end_date'],
			    'level' => $form['level'],
				'country'=> $form['country'],
			    'location' => $form['location'],
			    'funding' => $form['funding'],
			    'govt_approval_no' => $form['govt_approval_no'],
			    'result' => $form['result'],
			    'author' => $this->_author,
				'created' => $this->_created,
				'modified' => $this->_modified,

				);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\TrainingTable::class)->save($data);

            if($result > 0):
                $this->flashMessenger()->addMessage("success^ New Training successfully added");
            else:
                $this->flashMessenger()->addMessage("Failed^ Failed to add new Training");
            endif;
            return $this->redirect()->toRoute('employee', array('action' => 'training','id'=>$this->_id));  
		}
		     	
            return new ViewModel(array(
            		'title' => 'Add Training',
            		'employees' => $this->getDefinedTable(Hr\EmployeeTable::class)->get($this->_id),
            ));
	} 
	
	/**
	 *Edit Training action
	 **/
	public function edittrainingAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form=$this->getRequest()->getPost();
			$data=array(	
					'id' =>$this->_id,
    			    'training_name' => $form['training_name'],
    			    'start_date' => $form['start_date'],
    			    'end_date' => $form['end_date'],
    			    'level' => $form['level'],
					'country' => $form['country'],
    			    'location' => $form['location'],
    			    'funding' => $form['funding'],
    			    'govt_approval_no' => $form['govt_approval_no'],
    			    'result' => $form['result'],
    			    'author' => $this->_author,
    			    'modified' => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\TrainingTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Training successfully Updated");
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to Update Training");
			endif;
			$employee_id = $this->getDefinedTable(Hr\TrainingTable::class)->getColumn($this->_id, 'employee');
			return $this->redirect()->toRoute('employee', array('action' => 'training','id'=> $employee_id));
		}
		if(!isset($this->_id) || $this->_id == 0):
			$this->redirect()->toRoute('employee');
		endif;
		return new ViewModel(array(
				'training'=>$this->getDefinedTable(Hr\TrainingTable::class)->get($this->_id),
		));
	}
	/**
	 *
	 * Update leave balance after encashment
	 * 
	 */
	 public function updateleavebalanceAction()
	 {
		$this->init();
                //echo "This is my testing"; exit;	
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest()->getPost();
			$empID = $form['empID'];
			$leave_balance_date = "2018-10-31";
			$leave_balance = $form['leave_balance'];
			$emplID = $form['emplID'];
		    $this->_connection->beginTransaction(); //***Transaction begins here***//
			for($i=0; $i < sizeof($leave_balance); $i++):
				$data=array(
					'id' 		    =>$empID[$i],
					'leave_balance' =>$leave_balance[$i],
					'emp_id'        =>$emplID[$i],
					'leave_balance_date' =>$leave_balance_date[$i],
					'modified'      =>$this->_modified,
				);
				$data = $this->_safedataObj->rteSafe($data);
				$result = $this->getDefinedTable(Hr\EmployeeTable::class)->save($data);
			endfor;
			if($result > 0):
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Leave Balance successfully updated");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("Failed^ Failed to edit Leave Balance");
			endif;
			return $this->redirect()->toRoute('employee',array('action' => 'index'));
		}
		return new ViewModel(array(
			'title' => 'Update Leave Balance',
			'employee' => $this->getDefinedTable(Hr\EmployeeTable::class)->getEmployee(array('e.status'=>'1')),			
		));
	 }
	 /**
	 *View paydetail action
	 **/
	public function viewpaydetailAction()
	{
		$this->init();	
		//echo $this->_id; exit;
		$empID = $this->_id;
		if($this->_id <= 0):
            $this->flashmessenger()->addMessage('notice^ Add employee details first');
            return $this->redirect()->toRoute('employee', array('action' => 'addemployee'));
		endif;
		if($this->getRequest()->isPost()):
			$request = $this->getRequest()->getPost();
			$year = $request['year'];
			$month = $request['month'];
		else:		
			$month =  date('m');
			$year = date('Y');
		endif;
		return new ViewModel(array(
				'title' => 'view paydetail',
				'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
				'year' => $year,
				'month' => $month,
				'empID' => $empID,
				'payrollObj' => $this->getDefinedTable(Hr\PayrollTable::class),
				'paydetailObj' => $this->getDefinedTable(Hr\PaydetailTable::class),
		));
	}
	
	/**
	 * checkavailability Action
	 * check if the cid no is already used
	**/
	public function getcheckavailabilityAction()
	{
		//$this->init(); //including $this->init() will check the PermissionPlugin
		$form = $this->getRequest()->getPost();
		
		if($form['employee_id']):
			$emp_old_cid = $this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($form['employee_id'],'cid');
			if($form['cid'] == $emp_old_cid):
				$result = TRUE;
			else:
				$cid_no = $form['cid'];
				$result = $this->getDefinedTable(Hr\EmployeeTable::class)->checkAvailability('cid', $cid_no);
			endif;
		else:
			$cid_no = $form['cid'];
			$result = $this->getDefinedTable(Hr\EmployeeTable::class)->checkAvailability('cid', $cid_no);
		endif;
		echo json_encode(array(
					'valid' => $result,
		));
		exit;
	}
		/**
	 * Get Gewog via dzo type
	 */
	public function getgewogAction()
	{		
		$form = $this->getRequest()->getPost();
		$dzoId = $form['dzoId'];
		$block = $this->getDefinedTable(Administration\BlockTable::class)->get(array('district'=>$dzoId));
		
		$gewog = "<option value=''></option>";
		foreach($block as $blocks):
			$gewog.="<option value='".$blocks['id']."'>".$blocks['block']."</option>";
		endforeach;
		
		echo json_encode(array(
				'gewog' => $gewog,
		));
		exit;
	}
	/**
	 * Get Village via Gewog type
	 */
	public function getvillageAction()
	{		
		$form = $this->getRequest()->getPost();
		$gewogId = $form['gewogId'];
		$village_list = $this->getDefinedTable(Administration\VillageTable::class)->get(array('block'=>$gewogId));
		
		$village = "<option value=''></option>";
		foreach($village_list as $village_lists):
			$village.="<option value='".$village_lists['id']."'>".$village_lists['village']."</option>";
		endforeach;
		echo json_encode(array(
				'village' => $village,
		));
		exit;
	}
	/**
	 * Get Location via Region type
	 */
	public function getlocationAction()
	{		
		$form = $this->getRequest()->getPost();
		$regionId = $form['regionId'];
		$location_list = $this->getDefinedTable(Administration\LocationTable::class)->get(array('region'=>$regionId));
		
		$location = "<option value=''></option>";
		foreach($location_list as $location_lists):
			$location.="<option value='".$location_lists['id']."'>".$location_lists['location']."</option>";
		endforeach;
		
		echo json_encode(array(
				'location' => $location,
		));
		exit;
	}
	/**
	 * Get activity via Region type
	 */
	public function getactivityAction()
	{		
		$form = $this->getRequest()->getPost();
		$deptId = $form['deptId'];
		$dept_list = $this->getDefinedTable(Administration\ActivityTable::class)->get(array('department'=>$deptId));
		
		$activity = "<option value=''></option>";
		foreach($dept_list as $dept_lists):
			$activity.="<option value='".$dept_lists['id']."'>".$dept_lists['activity']."</option>";
		endforeach;
		echo json_encode(array(
				'activity' => $activity,
		));
		exit;
	}
	/*Get details using cid for api*/
	public function getciddtlAction()
	{   //echo 'hellow';exit;
		$this->init();
		$form = $this->getRequest()->getPost();
		$cid_no = $form['cid'];
		$url = $this->_config['ditt_api_census'];
		$census_url = $url."citizenAPI/index.php";
		$data = array(
			'cid' => $cid_no,
		);
		$records = $this->ApiPlugin()->sendApiData($census_url,$data);
		//records is get from ApiPlugin
$row = null; // Initialize to avoid undefined variable warning
		foreach($records as $temp_row):
			$row = $temp_row;
		endforeach;
		if(strlen($row['cid']) > 0 ){
			if(!empty ( $row['lastName'] )){
			   $full_name = (isset($row['middleName']))?$row['firstName']." ".$row['middleName']." ".$row['lastName']:$row['firstName']." ".$row['lastName'];
			}else{
				$full_name = $row['firstName'];
			}
			$full_name_dzo = $row['firstDzoName'];
			$gender = ($row['gender']=='M')?'Male':'Female';
			$dob = strtr($row['dob'], '/', '-');
			$dob = date('Y-m-d', strtotime($dob));
			echo json_encode(array(
				'cid'     => $row['cid'],
				'name'    => $full_name,
				'name_dzo'=> $full_name_dzo,
				'dob'     => $dob,
				'gender'  => $gender,
				'fatherName'=>$row['fatherName'],
				'motherName'=>$row['motherName'],
				'thram_no'=> $row['thramNo'],
				'house_no'=> $row['houseNo'],
				'gewog'   => $row['gewogName'],
				'dzongkhag'=>$row['dzongkhagName'],
				'gewog'=>$row['gewogName'],
				'village'=>$row['villageName'],
			));
		}else{
			echo json_encode(array(
			'message' => "<span style='color:red;font-size:12px;'>". $cid_no. " does not exist in Census Database.</span>",
			));
		}
		exit;
	}

}
?>

