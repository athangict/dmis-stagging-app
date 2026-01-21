<?php
namespace Academic\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class ExaminationTable extends AbstractTableGateway 
{
	protected $table = 'aca_examination'; //tablename

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
	public function get($param)
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
	    $columns="";
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
	public function getMin($column)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'min' => new Expression('MIN('.$column.')')
		));
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		$columns = null; // Initialize to avoid undefined variable warning
		foreach ($results as $result):
		$column =  $result['min'];
		endforeach;
	
		return $column;
	}
	/**
	 * Return records of given condition array
	 * @param Array $data
	 * @return Array
	 */
	public function getAllReport($data,$column,$where = NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
	    $select = $sql->select();
		$select->from(array('e'=>$this->table))
		->join(array('st'=>'aca_stddetails'), 'st.std_id=e.std_id', array('organization','course','std_id'=>'std_id'));
		//->join(array('emp'=>'hr_employee'), 'st.std_id=emp.id', array('std_id'=> 'id'));
		$select->columns(array(
				'distinct' => new Expression('DISTINCT('.$column.')')
		));
		
		if($data['organization'] != '-1'){
			$select->where(array('e.organization'=>$data['organization']));
		}
		if($data['class'] != '-1'){
			$select->where(array('e.class'=>$data['class']));
		}
		//$select->where(array('e.yoe'=>$data['year']));
		if($where != NULL):
			$select->where($where);
		endif;		
	     $selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}	
	/**
	 * Return records of given condition array
	 * @param Array $data
	 * @return Array
	 */
	public function getDistinct($column,$where = NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
	    $select = $sql->select();
		$select->from(array('e'=>$this->table))
		->join(array('st'=>'aca_stddetails'), 'st.std_id=e.std_id', array('organization','course','std_id'=>'std_id'));
		$select->columns(array(
				'distinct' => new Expression('DISTINCT('.$column.')')
		));
		if($where != NULL):
			$select->where($where);
		endif;		
	     $selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}	
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getPesonalReport($param)
	{
		$where = ( is_array($param) )? $param: array('e.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('e'=>$this->table))
				->join(array('st'=>'aca_stddetails'), 'st.std_id=e.std_id', array('std_id'=>'std_id'))
				->join(array('emp'=>'hr_employee'), 'st.std_id=emp.id', array('village','full_name','cid','dob','std_id'=> 'id'))
		        ->where($where);
		
		 $selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
	}
	
}
