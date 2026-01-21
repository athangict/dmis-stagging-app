<?php
namespace Hr\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class EmpHistoryTable extends AbstractTableGateway 
{
	protected $table = 'hr_emp_history'; //tablename

	public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }	

	/**
	 * Return All records of table
	 * @return Array
	 */
	public function getAll()
	{  
	    $adapter = $this->adapter;-

	    $sql = new Sql($adapter);
	    $select = $sql->select();
	    $select->from(array('eh'=>$this->table))
				->join(array('e'=>'hr_employee'), 'e.id=eh.employee', array('emp_id','full_name'))
				->join(array('d'=>'adm_department'), 'd.id=eh.department', array('department_id'=>'id','department'))
				->join(array('pt'=>'hr_post_title'), 'pt.id=eh.position_title', array('position_title_id'=>'id','position_title' => 'position_title'))
				->join(array('pl'=>'hr_post_level'), 'pl.id=eh.position_level', array('position_level_id'=>'id','position_level' => 'position_level'))
				->join(array('a'=>'hr_appointment_type'), 'a.id=eh.type_of_appointment', array('apt_type_id'=>'id','app_type'=>'type_of_appointment'))
				->join(array('l'=>'adm_location'), 'l.id=eh.location', array('location_id'=>'id','location'))
	    		->join(array('r'=>'adm_region'), 'r.id=l.region', array('region_id'=> 'id','region'))
	    		->join(array('act'=>'adm_activity'), 'act.id=eh.activity', array('activity_id'=> 'id','activity'))
	    		->join(array('et'=>'hr_employee_type'), 'et.id=eh.employee_type', array('emp_type_id'=> 'id','type'=>'emp_type', 'type_code'=>'code'));
	    
	    $selectString = $sql->getSqlStringForSqlObject($select);
	    $results = $adapter->query($seslectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	    return $results;
	}
	
	/**
	 * Return records of given condition array | given id
	 * @param Int $param | Array $param
	 * @return Array
	 */
	public function get($param)
	{
		$where = ( is_array($param) )? $param: array('eh.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		 $select->from(array('eh'=>$this->table))
				->join(array('e'=>'hr_employee'), 'e.id=eh.employee', array('emp_id','full_name'))
				->join(array('d'=>'adm_department'), 'd.id=eh.department', array('department_id'=>'id','department'))
				->join(array('pt'=>'hr_post_title'), 'pt.id=eh.position_title', array('position_title_id'=>'id','position_title' => 'position_title'))
				->join(array('pl'=>'hr_post_level'), 'pl.id=eh.position_level', array('position_level_id'=>'id','position_level' => 'position_level'))
				->join(array('a'=>'hr_appointment_type'), 'a.id=eh.type_of_appointment', array('apt_type_id'=>'id','app_type'=>'type_of_appointment'))
				->join(array('l'=>'adm_location'), 'l.id=eh.location', array('location_id'=>'id','location'))
	    		->join(array('r'=>'adm_region'), 'r.id=l.region', array('region_id'=> 'id','region'))
	    		->join(array('act'=>'adm_activity'), 'act.id=eh.activity', array('activity_id'=> 'id','activity'))	    		
	    		->join(array('et'=>'hr_employee_type'), 'et.id=eh.employee_type', array('emp_type_id'=> 'id','type'=>'emp_type', 'type_code'=>'code'))
		       ->where($where);
		
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
	 * Save record
	 * @param String $array
	 * @return Int
	 */
	public function save($data)
	{
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
	* 
	*/
	public function isPresent($column, $value)
	{
		$column = $column; $value = $value;
		$resultSet = $this->select(function(Select $select) use ($column, $value){
			$select->where(array($column => $value));
		});
		
		$resultSet = $resultSet->toArray();
		return (sizeof($resultSet)>0)? TRUE:FALSE;
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
	public function getMax($column, $where = NULL)
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
	 * Return records of given condition array
	 * @param Int $column
	 * @param Int $param
	 * @return Array
	 */
	public function getMaxRow($column, $param)
	{
		$where = ( is_array($param) )? $param: array('eh.id' => $param);
		$adapter = $this->adapter;
		$sub0 = new Select(array('eh'=>$this->table));
		$sub0->columns(array(
				$column => new Expression('MAX('.$column.')')
		));
		$sub0->where($where);
		
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('eh'=>$this->table))
				->join(array('e'=>'hr_employee'), 'e.id=eh.employee', array('emp_id','full_name'))
				->join(array('d'=>'adm_department'), 'd.id=eh.department', array('department_id'=>'id','department'))
				->join(array('pt'=>'hr_post_title'), 'pt.id=eh.position_title', array('position_title_id'=>'id','position_title' => 'position_title'))
				->join(array('pl'=>'hr_post_level'), 'pl.id=eh.position_level', array('position_level_id'=>'id','position_level' => 'position_level', 'min_pay', 'increment','max_pay'))
				->join(array('a'=>'hr_appointment_type'), 'a.id=eh.type_of_appointment', array('apt_type_id'=>'id','app_type'=>'type_of_appointment'))
				->join(array('l'=>'adm_location'), 'l.id=eh.location', array('location_id'=>'id','location'))
	    		->join(array('r'=>'adm_region'), 'r.id=l.region', array('region_id'=> 'id','region'))
	    		->join(array('act'=>'adm_activity'), 'act.id=eh.activity', array('activity_id'=> 'id','activity'))
	    		->join(array('et'=>'hr_employee_type'), 'et.id=eh.employee_type', array('emp_type_id'=> 'id','type'=>'emp_type', 'type_code'=>'code'))
				->where($where)
				->where->in('eh.'.$column, $sub0);
	
		$selectString = $sql->getSqlStringForSqlObject($select);	
		//echo $selectString; exit; 
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * Return records of given condition array
	 * @param Int $column
	 * @param Int $param
	 * @return Array
	 */
	public function getMinRow($column,$param)
	{
		$where = ( is_array($param) )? $param: array('eh.id' => $param);
		$adapter = $this->adapter;
	
		$sub0 = new Select($this->table);
		$sub0->columns(array(
				$column => new Expression('MIN('.$column.')')
		));
		//$sub0 = $sub0->toArray();
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('eh'=>$this->table))
				->join(array('e'=>'hr_employee'), 'e.id=eh.employee', array('emp_id','full_name'))
				->join(array('d'=>'adm_department'), 'd.id=eh.department', array('department_id'=>'id','department'))
				->join(array('pt'=>'hr_post_title'), 'pt.id=eh.position_title', array('position_title_id'=>'id','position_title' => 'post_title'))
				->join(array('pl'=>'hr_post_level'), 'pl.id=eh.position_level', array('position_level_id'=>'id','position_level' => 'post_level'))
				->join(array('a'=>'hr_appointment_type'), 'a.id=eh.type_of_appointment', array('apt_type_id'=>'id','app_type'=>'type_of_appointment'))
				->join(array('l'=>'adm_location'), 'l.id=eh.location', array('location_id'=>'id','location'))
	    		->join(array('r'=>'adm_region'), 'r.id=l.region', array('region_id'=> 'id','region'))
	    		->join(array('act'=>'adm_activity'), 'act.id=eh.activity', array('activity_id'=> 'id','activity'))
	    		->join(array('ps'=>'hr_pay_scale'), 'ps.id=eh.pay_scale', array('pay_scale_id'=> 'id','pay_scale'))
	    		->join(array('et'=>'hr_employee_type'), 'et.id=eh.employee_type', array('emp_type_id'=> 'id','type'=>'emp_type', 'type_code'=>'code'))
		->where($where)
		->where($column);
	
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
}
