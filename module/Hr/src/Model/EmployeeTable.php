<?php
namespace Hr\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;
use Laminas\Db\Sql\Expression;
use Laminas\Db\TableGateway\TableGateway;

class EmployeeTable extends AbstractTableGateway 
{
	protected $table = 'hr_employee'; //tablename

	public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }	
       /**
	 * Return All records of table
	 * @return Array
	 */
	public function getAllEmployee($locations=NULL, $selected_status=-1)//$emp_id=0, 
	{  
        // print_r($locations); exit;	
         $adapter = $this->adapter;
	    $sql = new Sql($adapter);
	    $select = $sql->select();
	    $select->from(array('e'=>$this->table))
				->join(array('s'=>'hr_employee_status'), 's.id = e.status', array('status', 'status_id' => 'id', 'status_color' => 'color'))
		        ->join(array('l'=>'adm_location'), 'l.id=e.location', array('location_id'=>'id','location'))
		        ->join(array('r'=>'adm_region'), 'r.id=l.region', array('region_id'=> 'id','region'))
	    		->join(array('et'=>'hr_employee_type'), 'et.id=e.type', array('emp_type_id'=> 'id','type'=>'emp_type', 'type_code'=>'code'));
	    $where = new Where();
	  //  if($emp_id>0):
		//	$where->equalTo('e.id',$emp_id);
		//endif;
		if(!empty($locations)):
			$where->OR
				->in('e.location', $locations);
		endif;	
		if($selected_status != '-1'):
			$where->equalTo('e.status',$selected_status);
		endif;	
		$select->where($where);
		//		->order(array('e.position_level ASC', 'e.emp_id ASC'));
	    $selectString = $sql->getSqlStringForSqlObject($select);
//	echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	    return $results;
	}
		/**
	 * Return All records of table
	 * @return Array
	 */
	public function getAll($locations=NULL, $selected_status=-1)//$emp_id=0, 
	{  
	    $adapter = $this->adapter;
	    $sql = new Sql($adapter);
	    $select = $sql->select();
	    $select->from(array('e'=>$this->table))
	           ->join(array('v'=>'adm_village'), 'v.id = e.village', array('village', 'village_id' => 'id'))
	           ->join(array('g'=>'adm_block'), 'g.id = v.block', array('block', 'gewog_id' => 'id'))
	           ->join(array('dz'=>'adm_district'), 'dz.id = g.district', array('district', 'dzongkhag_id' => 'id'))
				->join(array('s'=>'hr_employee_status'), 's.id = e.status', array('status', 'status_id' => 'id', 'status_color' => 'color'))
		       ->join(array('d'=>'adm_department'), 'd.id=e.department', array('department_id'=>'id','department'))
		       ->join(array('pt'=>'hr_post_title'), 'pt.id=e.position_title', array('position_title_id'=>'id','position_title' => 'position_title'))
		       ->join(array('pl'=>'hr_post_level'), 'pl.id=e.position_level', array('position_level_id'=>'id','position_level' => 'position_level'))
		       ->join(array('l'=>'adm_location'), 'l.id=e.location', array('location_id'=>'id','location'))
		       ->join(array('r'=>'adm_region'), 'r.id=l.region', array('region_id'=> 'id','region'))
	    		->join(array('act'=>'adm_activity'), 'act.id=e.activity', array('activity_id'=> 'id','activity'))
	    		->join(array('et'=>'hr_employee_type'), 'et.id=e.type', array('emp_type_id'=> 'id','type'=>'emp_type', 'type_code'=>'code'));
	    $where = new Where();
	  //  if($emp_id>0):
		//	$where->equalTo('e.id',$emp_id);
		//endif;
		if(!empty($locations)):
			$where->OR
				->in('e.location', $locations);
		endif;	
		if($selected_status != '-1'):
			$where->equalTo('e.status',$selected_status);
		endif;	
		$select->where($where)
				->order(array('e.position_level ASC', 'e.emp_id ASC'));
	    $selectString = $sql->getSqlStringForSqlObject($select);
		///echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	    return $results;
	}
	
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function get($param)
	{
		$where = ( is_array($param) )? $param: array('e.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('e'=>$this->table))
		       ->join(array('v'=>'adm_village'), 'v.id = e.village', array('village', 'village_id' => 'id'))
	           ->join(array('g'=>'adm_block'), 'g.id = v.block', array('block', 'gewog_id' => 'id'))
	           ->join(array('dz'=>'adm_district'), 'dz.id = g.district', array('district', 'dzongkhag_id' => 'id'))
		       ->join(array('s'=>'hr_employee_status'), 's.id = e.status', array('status', 'status_id' => 'id', 'status_color' => 'color'))
		       ->join(array('d'=>'adm_department'), 'd.id=e.department', array('department_id'=>'id','department'))
		       ->join(array('pt'=>'hr_post_title'), 'pt.id=e.position_title', array('position_title_id'=>'id','position_title' => 'position_title'))
		       ->join(array('pl'=>'hr_post_level'), 'pl.id=e.position_level', array('position_level_id'=>'id','position_level' => 'position_level'))
		       ->join(array('l'=>'adm_location'), 'l.id=e.location', array('location_id'=>'id','location'))
		       ->join(array('r'=>'adm_region'), 'r.id=l.region', array('region_id'=> 'id','region'))
	    		->join(array('act'=>'adm_activity'), 'act.id=e.activity', array('activity_id'=> 'id','activity'))
	    		->join(array('et'=>'hr_employee_type'), 'et.id=e.type', array('emp_type_id'=> 'id','type'=>'emp_type', 'type_code'=>'code'))
	           ->where($where)
				->order(array('e.position_level ASC'));
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getEmployees()
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return column value of given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
    public function getColumn($param, $column)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$fetch = array($column);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns($fetch);
		$select->where($where);
	
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		$columns = null; // Initialize to avoid undefined variable warning
		foreach ($results as $result):
			$columns =  $result[$column];
		endforeach;
		 
		return $columns;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getEmployee($param)
	{
		$where = ( is_array($param) )? $param: array('e.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('e'=>$this->table))
		       ->join(array('s'=>'hr_employee_status'), 's.id = e.status', array('status', 'status_id' => 'id', 'status_color' => 'color'))
		       ->join(array('l'=>'adm_location'), 'l.id=e.location', array('location_id'=>'id','location'))
		       ->join(array('r'=>'adm_region'), 'r.id=l.region', array('region_id'=> 'id','region'))
	    		->join(array('et'=>'hr_employee_type'), 'et.id=e.type', array('emp_type_id'=> 'id','type'=>'emp_type', 'type_code'=>'code'))
	           ->where($where);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
          //      echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
	}
	/**
	 * Save record
	 * @param String $array
	 * @return Int
	 */
	public function save($data)
	{
	    if(is_array($data[0])):
			$data1 = $data[0];
			$data2 = $data[1];
			$connection = $this->getAdapter()->getDriver()->getConnection();
			$connection->beginTransaction();
			//insertion or updation into employee table
			$id = isset($data1['id']) ? (int)$data1['id'] : 0;
			if ( $id > 0 )
			{
				$result = ($this->update($data1, array('id'=>$id)))?$id:0;
			} else {
				$this->insert($data1);
				$result = $this->getLastInsertValue(); 
			}
			//insertion or updation into employee history table
			$emphisTable = new TableGateway('hr_emp_history', $this->adapter);
			$his_id = isset($data2['id']) ? (int)$data2['id'] : 0;
			if ( $his_id > 0 )
			{
				$result1 = ($emphisTable->update($data2, array('id'=>$his_id)))?$his_id:0;
			} else {
				$data2['employee']=$result;
				$emphisTable->insert($data2);
				$result1 = $emphisTable->getLastInsertValue();
			}
			if($result1>0){
				$connection->commit();
				return $result;
			}else{
				$connection->rollback();
				return 0;
			}
	    else:
			if ( !is_array($data) ) $data = $data->toArray();
			$id = isset($data['id']) ? (int)$data['id'] : 0;
			
			if ( $id > 0 )
			{
				$result = ($this->update($data, array('id'=>$id)))?$id:0;
			} else {
				$this->insert($data);
				$result = $this->getLastInsertValue(); 
			}	    	    
			return $result;	
	    endif;     
	}

	/**
     *  Delete a record
     *  @param int $id
     *  @return true | false
     */
	public function remove($id)
	{
		return $this->delete(array('id' => $id));
	}
	
	/**
	* check particular row is present in the table 
	* with given column and its value
	* @param Array $where
	* @return Boolean
	* 
	*/
	public function isPresent($where)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			->where($where);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return (sizeof($results)>0)? TRUE:FALSE;
	} 

	/**
	 * Return Min value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getMin($column, $where = NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'min' => new Expression('MIN('.$column.')')
		));
		if($where!=NULL){
			$select->where($where);
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		$columns = null; // Initialize to avoid undefined variable warning
		foreach ($results as $result):
		$column =  $result['min'];
		endforeach;
	
		return $column;
	}
	
	/**
	 * Return max value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getMax($column, $where=NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'max' => new Expression('MAX('.$column.')')
		));
		if($where!=NULL){
			$select->where($where);
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		$columns = null; // Initialize to avoid undefined variable warning
		foreach ($results as $result):
		$column =  $result['max'];
		endforeach;
	
		return $column;
	}
	
	/**
	 * Return max empid of the column
	 * @param String $year
	 * @return String | Int
	 */
	public function getLastEmpId($year)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'max' => new Expression('MAX(emp_id)')
		));
		
		$select->where->like('emp_id',$year."%");
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		$columns = null; // Initialize to avoid undefined variable warning
		foreach ($results as $result):
		$column =  $result['max'];
		endforeach;
	
		return (int)$column;
	}
	
	/**
	 * Return employees by status
	 * @param Array $status
	 * @return Array
	 */
	public function getEmpByStatus($status)
	{
		$status = (is_array($status))?$status:array($status);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			->where->in('status', $status);	
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
	}
	/**
	 * Return employees for reporting
	 * @param Array $data
	 * @return Array
	 */
	public function getEmpforReport($data)
	{
		extract($data);
		$status = array(1,4,5);		
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('e'=>$this->table))
			->join(array('pt'=>'hr_post_title'), 'pt.id=e.position_title', array('position_title_id'=>'id','position_title' => 'position_title'))
			->join(array('pl'=>'hr_post_level'), 'pl.id=e.position_level', array('position_level_id'=>'id','position_level' => 'position_level'))
			->join(array('d'=>'adm_department'), 'd.id=e.department', array('department_id'=>'id','department'));
		
		if($department != -1):
			if($location != -1):
				$select->where(array('e.department'=>$department, 'location'=>$location));
			else:
				if($region != -1):
					$select->where(array('e.department' => $department));
					$subselect = new Select('adm_location');
					$subselect->columns(array('id'));
					$subselect->where(array('region' => $region));
					$select->where->in('location', $subselect);
				else:
					$select->where(array('e.department' => $department));
				endif;
			endif;
		else:
			if($location != -1):
				$select->where(array('location'=>$location));
			else:
				if($region != -1):
					$subselect = new Select('adm_location');
					$subselect->columns(array('id'));
					$subselect->where(array('region' => $region));
					$select->where->in('location', $subselect);
				endif;
			endif;
		endif;
		$select->where->in('status', $status);
	
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		return $results;
	}
	/**
	 * Return id's|columns'value  which is not present in given array
	 * @param Array $param
	 * @param String column
	 * @return Array
	 */
	public function getNotIn($param, $column='id', $where=NULL)
	{
		$param = ( is_array($param) )? $param: array($param);
		$where = (is_array($column)) ? $column: $where;
		$column = (is_array($column)) ? 'id' : $column;
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = new Select();
		$select->from($this->table)
		->columns(array('id'))
		->where->notIn($column, $param);
		if ($where != Null)
		{
			$select->where($where);
		}
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
	}
	
	/**
	 * check particular row is present in the table 
	 * with given column and its value
	 * MODIFIED FOR FROM VALIDATION (REMOTE VALIDATOR) 
	**/
	public function checkAvailability($column, $value)
	{
		$column = $column; $value = $value;
		$resultSet = $this->select(function(Select $select) use ($column, $value){
			$select->where(array($column => $value));
		});
		
		$resultSet = $resultSet->toArray();
		return (sizeof($resultSet)>0)?FALSE:TRUE;
	}  
	/**
	 * HR GENERAL REPORT
	**/
	public function getHreport($param)
	{
		//$where = ( is_array($param) )? $param: array('e.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('e'=>$this->table))
		       ->join(array('s'=>'hr_employee_status'), 's.id = e.status', array('status', 'status_id' => 'id', 'status_color' => 'color'))
		       ->join(array('d'=>'adm_department'), 'd.id=e.department', array('department_id'=>'id','department'))
		       ->join(array('pt'=>'hr_post_title'), 'pt.id=e.position_title', array('position_title_id'=>'id','position_title' => 'position_title'))
		       ->join(array('pl'=>'hr_post_level'), 'pl.id=e.position_level', array('position_level_id'=>'id','position_level' => 'position_level'))
		       ->join(array('l'=>'adm_location'), 'l.id=e.location', array('location_id'=>'id','location'))
		       ->join(array('r'=>'adm_region'), 'r.id=l.region', array('region_id'=> 'id','region'))
	    	   ->join(array('act'=>'adm_activity'), 'act.id=e.activity', array('activity_id'=> 'id','activity'))
	    	   ->join(array('et'=>'hr_employee_type'), 'et.id=e.type', array('emp_type_id'=> 'id','type'=>'emp_type', 'type_code'=>'code'))
			   ->join(array('v'=>'adm_village'), 'v.id = e.village', array('village', 'village_id' => 'id'))
	           ->join(array('g'=>'adm_block'), 'g.id = v.block', array('block', 'gewog_id' => 'id'))
	           ->join(array('dz'=>'adm_district'), 'dz.id = g.district', array('district', 'dzongkhag_id' => 'id'))
			   //->where($where)
			   ->order(array('e.position_level ASC'));
                                if($param['region'] != '-1'){
					$select->where(array('r.id'=>$param['region']));
				}
				if($param['location'] != '-1'){
					$select->where(array('e.location'=>$param['location']));
				}
				if($param['department'] != '-1'){
					$select->where(array('e.department'=>$param['department']));
				}
				if($param['activity'] != '-1'){
					$select->where(array('e.activity'=>$param['activity']));
				}
				if($param['position_level'] != '-1'){
					$select->where(array('e.position_level'=>$param['position_level']));
				}
				if($param['emp_status'] != '-1'){
					$select->where(array('e.status'=>$param['emp_status']));
				}
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
	}
	/**
	 * Return All records of table
	 * @return Array
	 */
	public function getTempEmp()
	{  
	    $adapter = $this->adapter;
	    $sql = new Sql($adapter);
	    $select = $sql->select();
	    $select->from($this->table);
	    
	    $selectString = $sql->getSqlStringForSqlObject($select);
	    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	    return $results;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getstd($param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
		       ->where($where);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**GET EMPLOYEE COUNT IN ALL ORG------------------------------------------------------------------ */
	/**GET STUDENT COUNT------------------------------------------------------------------------------------------------------------ */
	/** 
	 * Return Count value of the column
	 * @param Array $where
	 * @return String | Int
	 */
	public function getCount($where = NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			->columns(array('count' => new Expression('COUNT(*)')));
		
		if($where != NULL):
			$select->where($where);
		endif;
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		$column = null; // Initialize to avoid undefined variable warning
		foreach($results as $row):
			$column = $row['count'];
		endforeach;
		return $column;
	}

}
