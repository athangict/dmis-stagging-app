<?php
/**
 * Plugin -- Permission Plugin
 * chophel@athang.com
 */
namespace Acl\Controller\Plugin;
 
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Laminas\Db\Adapter\Adapter;    
use Laminas\Authentication\AuthenticationService;
use Laminas\Mvc\MvcEvent;
use Laminas\Http\Response;
use Interop\Container\ContainerInterface;

class PermissionPlugin extends AbstractPlugin{
	protected $_container;
	protected $dbAdapter;
	
	public function __construct(ContainerInterface $container)
    {
        $this->_container = $container;
    }
	/**
	 * Permission Access
	 */
	public function permission(MvcEvent $e, $permission=NULL){
		$auth = new AuthenticationService();
		/** Get Database Adapter **/
		$this->dbAdapter = $this->_container->get('Laminas\Db\Adapter\Adapter');
		if ($auth->hasIdentity()) {
			$login_id = $auth->getIdentity()->id;
		
$role = $auth->getIdentity()->role;
		$login_role_array = !empty($role) ? explode(',',$role) : array();
		$login_role = (sizeof($login_role_array)>0)?$role:0;

		$adminLocation = $auth->getIdentity()->admin_location;
		$admin_location = !empty($adminLocation) ? explode(',',$adminLocation) : array();
		
		$adminActivity = $auth->getIdentity()->admin_activity;
		$admin_activity = !empty($adminActivity) ? explode(',',$adminActivity) : array();
		}else{
			$login_id = 0;
			$login_role = 1;
			$admin_location = array(0);	
			$admin_activity = array(0);	
		}
		
		/** Get Highest Role **/
		$hrQuery ="SELECT MAX(`id`) as `role` FROM `sys_roles`";
		$hr_stmt = $this->dbAdapter->query($hrQuery);
        $hrole = $hr_stmt->execute();
        $hrow = null; // Initialize to avoid undefined variable warning
        foreach($hrole as $row):
        	$hrow = $row;
        endforeach;
		$highest_role = $hrow['role'];
		
		/** Get Currently Accessed Resources **/
		$controller = $e->getTarget();
		$controllerClass = get_class($controller);
		$moduleName = strtolower(substr($controllerClass, 0, strpos($controllerClass, '\\')));
		
		$routeMatch = $e->getRouteMatch();
		$actionName = strtolower($routeMatch->getParam('action', 'not-found'));	/** get the action name **/
		
		$controllerName = $routeMatch->getParam('controller', 'not-found');	/** get the controller name **/
		$controllerName = explode("\\", $controllerName);
		$controllerName = strtolower(array_pop($controllerName));
		$controllerName = substr($controllerName, 0, -10);
		
		$routeName = $routeMatch->getMatchedRouteName();
		$routeName = (strpos($routeName, '/') !== false)?substr($routeName, 0, strpos($routeName, "/")):$routeName;
		
		$routeParamID = $routeMatch->getParam('id');
		$routeParamID = !empty($routeParamID) ? explode('_', $routeParamID) : array();
		$id = isset($routeParamID[0]) ? $routeParamID[0] : 0;
		
		$aclQuery = "SELECT * FROM `sys_acl` WHERE `route`='".$routeName."' AND `controller`='".$controllerName."' AND `action`='".$actionName."' AND `resource` IN (SELECT `id` FROM `sys_modules` WHERE `module`='".$moduleName."')";
		$acl_stmt = $this->dbAdapter->query($aclQuery);
		$acldetails = $acl_stmt->execute();
		$arow = null; // Initialize to avoid undefined variable warning
		foreach($acldetails as $row):
			$arow = $row;
		endforeach;
		$acl_id = $arow['id'];
		if($arow['system']==0):
		$process_id = $arow['process'];
		$location_permit = '-1';
		$activity_permit = '-1';
		$onlyifcreator_permit = '-1';
		$status_permit = '-1';
		if(!in_array($highest_role,$login_role_array)){
			if($process_id>0):
				$processQuery = "SELECT * FROM `sys_process` WHERE `id`='".$process_id."'";
				$process_stmt = $this->dbAdapter->query($processQuery);
				$processdetails = $process_stmt->execute();
				$prow = null; // Initialize to avoid undefined variable warning
				foreach($processdetails as $row):
					$prow = $row;
				endforeach;
				$table_name = $prow['table_name'];
				
				$roleprocessQuery = "SELECT * FROM `sys_role_process` WHERE `process`='".$process_id."' AND `role` IN (".$login_role.")";
				$roleprocess_stmt = $this->dbAdapter->query($roleprocessQuery);
				$roleprocess = $roleprocess_stmt->execute();
				if($id != 0): /** start -- if id!=0 **/
					$idQuery = "SELECT * FROM `".$table_name."` WHERE `id`=".$id;
					$idStmt = $this->dbAdapter->query($idQuery);
					$records = $idStmt->execute();
					if(sizeof($records)>0):
						if(sizeof($roleprocess)>0):
							$location_column = array();
							$activity_column = array();
							$onlyifcreator_column = array();
							$permission_column = array();
							$status_column = array();
							foreach($roleprocess as $rprow):
								array_push($location_column,$rprow['location']);
								array_push($activity_column,$rprow['activity']);
								array_push($onlyifcreator_column,$rprow['only_if_creator']);
								array_push($status_column,$rprow['status']);
								array_push($permission_column,$rprow['permission_level']);
							endforeach;
							$location_permit = (in_array("0",$location_column))?'-1':$admin_location;
							$activity_permit = (in_array("0",$activity_column))?'-1':$admin_activity;
							$onlyifcreator_permit = (in_array("0",$onlyifcreator_column))?'-1':$login_id;
							$status_permit = (in_array("0",$status_column))?'-1':$permission_column;
							
							$column_array = array();
							if($location_permit != '-1'): array_push($column_array,'location');endif;
							if($activity_permit != '-1'): array_push($column_array,'activity');endif;
							if($onlyifcreator_permit != '-1'): array_push($column_array,'author');endif;
							if($status_permit != '-1'): array_push($column_array,'status');endif;
							$column = '';
							for($i=0;$i<sizeof($column_array);$i++):
								$column .= $column_array[$i];
								$column .= ($i != sizeof($column_array)-1)?", ":"";
							endfor;
							if(sizeof($column_array)>0):
								$Query = "SELECT `".$column."` FROM `".$table_name."` WHERE `id`=".$id;
								$Stmt = $this->dbAdapter->query($Query);
								$columndetails = $Stmt->execute();
								if(sizeof($columndetails)>0):
								$crow = null; // Initialize to avoid undefined variable warning
								foreach($columndetails as $row):
									$crow = $row;
								endforeach;
									$check_array = array();
									if($location_permit != '-1'): $check = (in_array($crow['location'],$location_permit))?'1':'0'; array_push($check_array,$check);endif;
									if($activity_permit != '-1'): $check = (in_array($crow['activity'],$activity_permit))?'1':'0'; array_push($check_array,$check);endif;
									if($onlyifcreator_permit != '-1'): $check = (in_array($crow['author'],$onlyifcreator_permit))?'1':'0'; array_push($check_array,$check);endif;
									if($status_permit != '-1'): $check = (in_array($crow['status'],$status_permit))?'1':'0'; array_push($check_array,$check);endif;
									$data = array(
										'location_permit'      => $location_permit,
										'activity_permit'      => $activity_permit,
										'onlyifcreator_permit' => $onlyifcreator_permit,
										'status_permit'        => $status_permit,
										'column_data'          => $crow,
									);
									if(in_array('0',$check_array)):
										$response = $e -> getResponse();
										$response -> getHeaders() -> addHeaderLine('Location', $e -> getRequest() -> getBaseUrl() . '/404');
										$response -> setStatusCode(404);
									endif;
								else:
									$permission = array(
											'location_permit' => $location_permit,
											'activity_permit' => $activity_permit,
											'onlyifcreator_permit' => $onlyifcreator_permit,
											'status_permit' => $status_permit,
									);
									return $permission;
								endif;
							else:
								$permission = array(
										'location_permit' => $location_permit,
										'activity_permit' => $activity_permit,
										'onlyifcreator_permit' => $onlyifcreator_permit,
										'status_permit' => $status_permit,
								);
								return $permission;
							endif;
						else:
							$permission = array(
									'location_permit' => $location_permit,
									'activity_permit' => $activity_permit,
									'onlyifcreator_permit' => $onlyifcreator_permit,
									'status_permit' => $status_permit,
							);
							return $permission;
						endif;
					else:
						$response = $e -> getResponse();
						$response -> getHeaders() -> addHeaderLine('Location', $e -> getRequest() -> getBaseUrl() . '/404');
						$response -> setStatusCode(404);
					endif;
				else: /** end -- if id!=0 / start -- id == 0 **/
					if(sizeof($roleprocess)>0):
						$location_column = array();
						$activity_column = array();
						$onlyifcreator_column = array();
						$permission_column = array();
						$status_column = array();
						foreach($roleprocess as $rprow):
							array_push($location_column,$rprow['location']);
							array_push($activity_column,$rprow['activity']);
							array_push($onlyifcreator_column,$rprow['only_if_creator']);
							array_push($status_column,$rprow['status']);
							array_push($permission_column,$rprow['permission_level']);
						endforeach;
						$location_permit = (in_array("0",$location_column))?'-1':$admin_location;
						$activity_permit = (in_array("0",$activity_column))?'-1':$admin_activity;
						$onlyifcreator_permit = (in_array("0",$onlyifcreator_column))?'-1':$login_id;
						$status_permit = (in_array("0",$status_column))?'-1':$permission_column;
						$permission = array(
								'location_permit' => $location_permit,
								'activity_permit' => $activity_permit,
								'onlyifcreator_permit' => $onlyifcreator_permit,
								'status_permit' => $status_permit,
						);
						return $permission;
					else:
						$permission = array(
								'location_permit' => $location_permit,
								'activity_permit' => $activity_permit,
								'onlyifcreator_permit' => $onlyifcreator_permit,
								'status_permit' => $status_permit,
						);
						return $permission;
					endif;
				endif;/** end -- if id==0 **/
			else:
				$permission = array(
						'location_permit' => $location_permit,
						'activity_permit' => $activity_permit,
						'onlyifcreator_permit' => $onlyifcreator_permit,
						'status_permit' => $status_permit,
				);
				return $permission;
			endif;
		}else{ /** start -- System Administrator Access **/
			if($process_id>0):
				$processQuery = "SELECT * FROM `sys_process` WHERE `id`='".$process_id."'";
				$process_stmt = $this->dbAdapter->query($processQuery);
				$processdetails = $process_stmt->execute();
				$prow = null; // Initialize to avoid undefined variable warning
				foreach($processdetails as $row):
					$prow = $row;
				endforeach;
				$table_name = $prow['table_name'];
				if($id != 0):
					$idQuery = "SELECT * FROM `".$table_name."` WHERE `id`=".$id;
					$idStmt = $this->dbAdapter->query($idQuery);
					$records = $idStmt->execute();
					if(sizeof($records)<=0):
						$response = $e -> getResponse();
						$response -> getHeaders() -> addHeaderLine('Location', $e -> getRequest() -> getBaseUrl() . '/404');
						$response -> setStatusCode(404);
					endif;
				else:
					$permission = array(
							'location_permit' => $location_permit,
							'activity_permit' => $activity_permit,
							'onlyifcreator_permit' => $onlyifcreator_permit,
							'status_permit' => $status_permit,
					);
					return $permission;
				endif;
			else:
				$permission = array(
						'location_permit' => $location_permit,
						'activity_permit' => $activity_permit,
						'onlyifcreator_permit' => $onlyifcreator_permit,
						'status_permit' => $status_permit,
				);
				return $permission;
			endif;
		} /** end -- System Administrator Access **/
		endif;
	}
	/**
	 * GET ALL
	 * PERMITTED ROLES - USER MANAGEMENT
	 */
	public function getrole(){
		$auth = new AuthenticationService();
		if ($auth->hasIdentity()) {
			$login_id = $auth->getIdentity()->id;
		
			$login_role_array = explode(',',$auth->getIdentity()->role);
			$login_role = (sizeof($login_role_array)>0)?$auth->getIdentity()->role:0;
		}else{
			$login_id = 0;
			$login_role = 1;
		}
		/** Get Database Adapter **/
		$this->dbAdapter = $this->_container->get('Laminas\Db\Adapter\Adapter');
		/** Get Highest Role **/
		$hrQuery ="SELECT MAX(`id`) as `role` FROM `sys_roles`";
		$hr_stmt = $this->dbAdapter->query($hrQuery);
        $hrole = $hr_stmt->execute();
        $hrow = null; // Initialize to avoid undefined variable warning
        foreach($hrole as $row):
        	$hrow = $row;
        endforeach;
		$highest_role = $hrow['role'];

		if(!in_array($highest_role,$login_role_array)){
			$roleQuery ="SELECT * FROM `sys_roles` WHERE `status` = '1' AND `id` NOT IN (1,".$highest_role.")";
		}else{
			$roleQuery ="SELECT * FROM `sys_roles` WHERE `status` = '1' AND `id` Not IN (1)";
		}
		$rolelist = array();
		$role_stmt = $this->dbAdapter->query($roleQuery);
		$roles = $role_stmt->execute(); 
		foreach($roles as $role):
			array_push($rolelist, $role);
		endforeach;
		return $rolelist;
	}
	/**
	 * GET ALL
	 * PERMITTED REGION
	 */
	public function getregion(){
		$auth = new AuthenticationService();
		if ($auth->hasIdentity()) {
			$login_role_array = explode(',',$auth->getIdentity()->role);
			$admin_location_array = explode(',',$auth->getIdentity()->admin_location);
			$admin_location = (sizeof($admin_location_array)>0 && !empty($admin_location_array))?$auth->getIdentity()->admin_location:0;
		}else{
			$login_role_array = array(0);
			$admin_location = 0;
		}
		/** Get Database Adapter **/
		$this->dbAdapter = $this->_container->get('Laminas\Db\Adapter\Adapter');
		/** Get Highest Role **/
		$hrQuery ="SELECT MAX(`id`) as `role` FROM `sys_roles`";
		$hr_stmt = $this->dbAdapter->query($hrQuery);
        $hrole = $hr_stmt->execute();
        $hrow = null; // Initialize to avoid undefined variable warning
        foreach($hrole as $row):
        	$hrow = $row;
        endforeach;
		$highest_role = $hrow['role'];
		
		$regionlist = array();
		if(!in_array($highest_role,$login_role_array)){
			//$regionQuery ="SELECT * FROM `adm_region` WHERE `status` = '1' AND `id` IN(SELECT DISTINCT `region` FROM `adm_location` WHERE `id` IN(".$admin_location."))";
			$regionQuery ="SELECT * FROM `adm_region` WHERE `status` = '1'";
		}else{
			$regionQuery ="SELECT * FROM `adm_region` WHERE `status` = '1'";
		}
		$region_stmt = $this->dbAdapter->query($regionQuery);
		$regions = $region_stmt->execute(); 
		foreach($regions as $region):
			array_push($regionlist, $region);
		endforeach;
		
		return $regionlist;
	}
	/**
	 * GET ALL & GET
	 * PERMITTED LOCATION
	 */
	public function getlocation($region_data = 0){
		$auth = new AuthenticationService();
		if ($auth->hasIdentity()) {
			$login_role_array = explode(',',$auth->getIdentity()->role);
			$admin_location_array = explode(',',$auth->getIdentity()->admin_location);
			$admin_location = (sizeof($admin_location_array)>0 && !empty($admin_location_array))?$auth->getIdentity()->admin_location:0;
		}else{
			$login_role_array = array(0);
			$admin_location = 0;
		}
		//get Database Adapter
		$this->dbAdapter = $this->_container->get('Laminas\Db\Adapter\Adapter');
		/** Get Highest Role **/
		$hrQuery ="SELECT MAX(`id`) as `role` FROM `sys_roles`";
		$hr_stmt = $this->dbAdapter->query($hrQuery);
        $hrole = $hr_stmt->execute();
        $hrow = null; // Initialize to avoid undefined variable warning
        foreach($hrole as $row):
        	$hrow = $row;
        endforeach;
		$highest_role = $hrow['role'];

		$locationlist = array();
		if(!in_array($highest_role,$login_role_array)){
			//$locationQuery ="SELECT * FROM `adm_location` WHERE `status` = '1' AND `id` IN(".$admin_location.")";
			//$locationQuery .= ($region_data!=0)?" AND region = '".$region_data."'":"";
			$locationQuery ="SELECT * FROM `adm_location` WHERE `status` = '1'";
			$locationQuery .= ($region_data!=0)?" AND region = '".$region_data."'":"";
		}else{
			$locationQuery ="SELECT * FROM `adm_location` WHERE `status` = '1'";
			$locationQuery .= ($region_data!=0)?" AND region = '".$region_data."'":"";
		}
		$location_stmt = $this->dbAdapter->query($locationQuery);
		$locations = $location_stmt->execute(); 
		foreach($locations as $location):
			array_push($locationlist, $location);
		endforeach;
        return $locationlist;
	}
	/**
	 * COUNT
	 * PERMITTED LOCATION COUNT
	 */
	public function getlocationCount($region_data = 0, $user_location = 0){
		$auth = new AuthenticationService();
		if ($auth->hasIdentity()) {
			$login_role_array = explode(',',$auth->getIdentity()->role);
			$admin_location_array = explode(',',$auth->getIdentity()->admin_location);
			$admin_location = (sizeof($admin_location_array)>0 && !empty($admin_location_array))?$auth->getIdentity()->admin_location:0;
		}else{
			$login_role_array = array(0);
			$admin_location = 0;
		}
		//get Database Adapter
		$this->dbAdapter = $this->_container->get('Laminas\Db\Adapter\Adapter');
		/** Get Highest Role **/
		$hrQuery ="SELECT MAX(`id`) as `role` FROM `sys_roles`";
		$hr_stmt = $this->dbAdapter->query($hrQuery);
        $hrole = $hr_stmt->execute();
        $hrow = null; // Initialize to avoid undefined variable warning
        foreach($hrole as $row):
        	$hrow = $row;
        endforeach;
		$highest_role = $hrow['role'];

		if(!in_array($highest_role,$login_role_array)){
			$locationQuery ="SELECT COUNT(*) AS `count` FROM `adm_location` WHERE `status` = '1' AND `id` IN(".$admin_location.")";
			$locationQuery .= ($region_data!=0)?" AND region = '".$region_data."'":"";
			$locationQuery .= ($user_location!=0)?" AND `id` IN (".$user_location.")":"";
		}else{
			$locationQuery ="SELECT COUNT(*) AS `count` FROM `adm_location` WHERE `status` = '1'";
			$locationQuery .= ($region_data!=0)?" AND region = '".$region_data."'":"";
			$locationQuery .= ($user_location!=0)?" AND `id` IN (".$user_location.")":"";
		}
		$location_stmt = $this->dbAdapter->query($locationQuery);
		$locations = $location_stmt->execute();
		$location = null; // Initialize to avoid undefined variable warning
		foreach($locations as $row):
			$location = $row;
		endforeach;
		$locationcount = $location['count'];
		
		return $locationcount;
	}
	/**
	 * GET ALL
	 * PERMITTED ACTIVITY
	 */
	public function getactivity(){
		$auth = new AuthenticationService();
		if ($auth->hasIdentity()) {
			$login_role_array = explode(',',$auth->getIdentity()->role);
			$admin_activity_array = explode(',',$auth->getIdentity()->admin_activity);
			$admin_activity = (sizeof($admin_activity_array)>0 && !empty($admin_activity_array))?$auth->getIdentity()->admin_activity:0;
		}else{
			$login_role_array = array(0);
			$admin_activity = 0;
		}
		//get Database Adapter
		$this->dbAdapter = $this->_container->get('Laminas\Db\Adapter\Adapter');
		/** Get Highest Role **/
		$hrQuery ="SELECT MAX(`id`) as `role` FROM `sys_roles`";
		$hr_stmt = $this->dbAdapter->query($hrQuery);
        $hrole = $hr_stmt->execute();
        $hrow = null; // Initialize to avoid undefined variable warning
        foreach($hrole as $row):
        	$hrow = $row;
        endforeach;
		$highest_role = $hrow['role'];

		$activitylist = array();
		if(!in_array($highest_role,$login_role_array)){
			//$activityQuery ="SELECT * FROM `adm_activity` WHERE `status` = '1' AND `id` IN(".$admin_activity.")";
			$activityQuery ="SELECT * FROM `adm_activity` WHERE `status` = '1'";
		}else{
			$activityQuery ="SELECT * FROM `adm_activity` WHERE `status` = '1'";
		}
		$activity_stmt = $this->dbAdapter->query($activityQuery);
		$activities = $activity_stmt->execute(); 
		foreach($activities as $activity):
			array_push($activitylist, $activity);
		endforeach;
		
		return $activitylist;
	}
	/**
	 * COUNT
	 * PERMITTED ACTIVITY COUNT
	 */
	public function getactivityCount(){
		$auth = new AuthenticationService();
		if ($auth->hasIdentity()) {
			$login_role_array = explode(',',$auth->getIdentity()->role);
			$admin_activity_array = explode(',',$auth->getIdentity()->admin_activity);
			$admin_activity = (sizeof($admin_activity_array)>0 && !empty($admin_activity_array))?$auth->getIdentity()->admin_activity:0;
		}else{
			$login_role_array = array(0);
			$admin_activity = 0;
		}
		//get Database Adapter
		$this->dbAdapter = $this->_container->get('Laminas\Db\Adapter\Adapter');
		/** Get Highest Role **/
		$hrQuery ="SELECT MAX(`id`) as `role` FROM `sys_roles`";
		$hr_stmt = $this->dbAdapter->query($hrQuery);
        $hrole = $hr_stmt->execute();
        $hrow = null; // Initialize to avoid undefined variable warning
        foreach($hrole as $row):
        	$hrow = $row;
        endforeach;
		$highest_role = $hrow['role'];

		if(!in_array($highest_role,$login_role_array)){
			$activityQuery ="SELECT COUNT(*) as `count` FROM `adm_activity` WHERE `status` = '1' AND `id` IN(".$admin_activity.")";
		}else{
			$activityQuery ="SELECT COUNT(*) as `count` FROM `adm_activity` WHERE `status` = '1'";
		}
		$activity_stmt = $this->dbAdapter->query($activityQuery);
		$activities = $activity_stmt->execute();
		$activity = null; // Initialize to avoid undefined variable warning
		foreach($activities as $row):
			$activity = $row;
		endforeach;
		$activitycount = $activity['count'];
		
		return $activitycount;
	}
}