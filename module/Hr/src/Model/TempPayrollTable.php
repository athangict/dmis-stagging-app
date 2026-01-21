<?php
namespace Hr\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class TempPayrollTable extends AbstractTableGateway 
{
	protected $table = 'hr_temp_payroll'; //tablename

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
	    $select->from(array('pr'=>$this->table))
				->join(array('emp'=>'hr_employee'), 'pr.employee = emp.id', array('employee_id'=>'id','full_name', 'emp_id', 'designation'))
				->join(array('his'=>'hr_emp_history'), 'his.id=pr.emp_his', array())
				->join(array('t'=>'hr_employee_type'), 'his.employee_type = t.id', array('emp_type_id'=>'id','emp_type', 'type_code'=>'code'))
				->join(array('l'=>'adm_location'), 'his.location = l.id', array('location_id'=>'id','location'))
				->join(array('r'=>'adm_region'),'r.id = l.region', array('region', 'region_id' => 'id'))
				->order(array('his.position_level ASC', 'emp.emp_id ASC'));
				
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
		$where = ( is_array($param) )? $param: array('pr.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('pr'=>$this->table))
				->join(array('emp'=>'hr_employee'), 'pr.employee = emp.id', array('employee_id'=>'id','full_name', 'emp_id', 'designation'))
				->join(array('his'=>'hr_emp_history'), 'his.id=pr.emp_his', array())
				->join(array('t'=>'hr_employee_type'), 'his.employee_type = t.id', array('emp_type_id'=>'id','emp_type', 'type_code'=>'code'))
				->join(array('l'=>'adm_location'), 'his.location = l.id', array('location_id'=>'id','location'))
				->join(array('r'=>'adm_region'),'r.id = l.region', array('region', 'region_id' => 'id'))
		       ->where($where)
		       ->order(array('his.position_level ASC'));
		
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
	 * function to insert and delete form hr_temp_payroll table
	 * if the employee status changes or new employee is added
	 */
	public function prepareTempPayroll($data)
	{
		extract($data);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		
		//delete from temp payroll if employee is resigned, retired or others....
		$emp = new Select('hr_employee');
		$emp->columns(array('employee'=>'id'))
				 ->where(array('status'=>array(1,4,5)));
				 
		$delete = $sql->delete();
		$delete->from($this->table);
		$delete->where->notin('employee',$emp);
		
		$deleteString = $sql->getSqlStringForSqlObject($delete);
		$del_result = $adapter->query($deleteString, $adapter::QUERY_MODE_EXECUTE);
		//end of delete
		
		//insertion of new employee if any
		$pr_employee = new Select($this->table);
		$pr_employee->columns(array('employee'));
		
		$new_employee = new Select('hr_employee');
		$new_employee->columns(array('employee'=>'id'))
					->where(array('status'=>array(1,4,5)))
					->where->notin('id', $pr_employee);
							
		$new_empString = $sql->getSqlStringForSqlObject($new_employee);
		
		$new_employees= $adapter->query($new_empString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		foreach($new_employees as $row):
				
			$emp_history = new Select(array('eh'=>'hr_emp_history'));
			$emp_history->columns(array(
					'start_date' => new Expression('MAX(start_date)'),
					'emp_his' => new Expression('MAX(id)')
			));
			$emp_history->where(array('employee'=>$row['employee']));
			
			$emp_historyString = $sql->getSqlStringForSqlObject($emp_history);		
			$emp_historys= $adapter->query($emp_historyString, $adapter::QUERY_MODE_EXECUTE)->toArray();
			$his_row = null; // Initialize to avoid undefined variable warning
			foreach($emp_historys as $row_his):
				$his_row = $row_his;
			endforeach;
			$new_data = array(
				'employee'=> $row['employee'],
				'emp_his'=> $his_row['emp_his'],
				'month'=> $month,
				'year'=> $year,
				'status' => '0',
				'working_days'=>date('t',strtotime('1-'.$month.'-'.$year)),
				'author'=>$author,
				'created'=>$created,
				'modified'=>$modified
			);
			$this->insert($new_data);
		endforeach;
		// end of insertion of new employee
		
		//update temp_payroll with latest employee history id
		foreach($this->getAll() as $tem_payroll):
			$emp_history = new Select(array('eh'=>'hr_emp_history'));
			$emp_history->columns(array(
					'start_date' => new Expression('MAX(start_date)'),
					'emp_his' => new Expression('MAX(id)')
			));
			$emp_history->where(array('employee'=>$tem_payroll['employee']));
			
			$emp_historyString = $sql->getSqlStringForSqlObject($emp_history);		
			$emp_historys= $adapter->query($emp_historyString, $adapter::QUERY_MODE_EXECUTE)->toArray();
			$his_row = null; // Initialize to avoid undefined variable warning
			foreach($emp_historys as $row_his):
				$his_row = $row_his;
			endforeach;
			
			$data = array(
				'emp_his'=>$his_row['emp_his'],
				'month'=>$month, 
				'year'=>$year, 
				'working_days'=> date('t',strtotime('1-'.$month.'-'.$year))
			);
			$this->update($data, array('employee'=>$tem_payroll['employee']));
		endforeach;
	}
	
	/**
	 * Return all payrollrecords in month if its payroll is prepared
	 * @return Array
	 */
	public function getPayroll($year)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table, array('month', 'year'))
			   ->group(array('month','year'));
		$select->order(array('month DESC', 'year DESC'));
		if($year != '-1'):
			$select->where(array('year' => $year));
		endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * PAY REGISTER
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getforReport($param)
	{
		$where = ( is_array($param) )? $param: array('pr.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('pr'=>$this->table))
				->join(array('e'=>'hr_employee'), 'pr.employee = e.id', array('employee_id'=>'id','full_name', 'emp_id', 'cid'))
				//->join(array('v'=>'hr_village'), 'v.id = e.village', array('village', 'village_id' => 'id'))
	           // ->join(array('g'=>'hr_gewog'), 'g.id = v.gewog', array('gewog', 'gewog_id' => 'id'))
	           //->join(array('dz'=>'hr_dzongkhag'), 'dz.id = g.dzongkhag', array('dzongkhag', 'dzongkhag_id' => 'id'))
		      ->join(array('his'=>'hr_emp_history'), 'his.id=pr.emp_his', array())
		        ->join(array('d'=>'sys_department'), 'd.id=his.department', array('department_id'=>'id','department'))
		        ->join(array('pt'=>'hr_post_title'), 'pt.id=his.position_title', array('position_title_id'=>'id','position_title' => 'position_title'))
		        ->join(array('pl'=>'hr_post_level'), 'pl.id=his.position_level', array('position_level_id'=>'id','position_level' => 'position_level','code'))
		        ->join(array('l'=>'adm_location'), 'l.id=his.location', array('location_id'=>'id','location'))
		        ->join(array('r'=>'adm_region'), 'r.id=l.region', array('region_id'=> 'id','region'))
	    	//	->join(array('act'=>'adm_activity'), 'act.id=his.activity', array('activity_id'=> 'id','activity'))
	    		->join(array('et'=>'hr_employee_type'), 'et.id=his.employee_type', array('emp_type_id'=> 'id','type'=>'emp_type', 'type_code'=>'code'))
		        ->where($where)
		        ->order(array('his.position_level ASC'));
		        
		$selectString = $sql->getSqlStringForSqlObject($select);
        //      echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * CONTROL SUMMARY
	 * Get Locations / include region also
	**/
	public function controlsummaryLocation($param)
	{
		$param = (is_array($param))? $param: array($param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('pr'=>$this->table))
				->columns(array())
				->join(array('his'=>'hr_emp_history'), 'his.id=pr.emp_his', array('location_id' => new Expression('Distinct(his.location)')))
				->join(array('l'=>'adm_location'), 'l.id=his.location', array('location'))
				->join(array('r'=>'adm_region'), 'r.id=l.region', array('region'))
				->where($param)
->order(array('l.location ASC'));
				
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * CONTROL SUMMARY
	 * Get Activity
	**/
	public function controlsummaryActivity($param)
	{
		$param = (is_array($param))? $param: array($param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('pr'=>$this->table))
				->columns(array())
				->join(array('his'=>'hr_emp_history'), 'his.id=pr.emp_his', array('activity_id' => new Expression('Distinct(his.activity)')))
				->join(array('act'=>'adm_activity'), 'act.id=his.activity', array('activity'))
				->where($param);
				
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * CONTROL SUMMARY
	 * Get No of Entries
	**/
	public function getTotalEntries($param,$status)
	{
		$param = (is_array($param))? $param: array($param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('pr'=>$this->table));
		$select->columns(array(
				'count' => new Expression('COUNT(pr.id)')
		));
		$select->join(array('his'=>'hr_emp_history'), 'his.id=pr.emp_his', array())
				->where($param);
		if($status == 'R'):
			$select->where->NotequalTo('his.position_level','19');
		else:
			$select->where->equalTo('his.position_level','19');
		endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		$columns = null; // Initialize to avoid undefined variable warning
		foreach ($results as $result):
			$column =  $result['count'];
		endforeach;
		return $column;
	}
	/**
	 * CONTROL SUMMARY
	 * Get Sum of Column
	**/
	public function getControlSummarySum($param,$status,$column)
	{
		$param = (is_array($param))? $param: array($param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('pr'=>$this->table));
		$select->columns(array(
				'sum' => new Expression('SUM('.$column.')')
		));
		$select->join(array('his'=>'hr_emp_history'), 'his.id=pr.emp_his', array())
				->where($param);
		if($status == 'R'):
			$select->where->NotequalTo('his.position_level','19');
		else:
			$select->where->equalTo('his.position_level','19');
		endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		$columns = null; // Initialize to avoid undefined variable warning
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
		return $column;
	}
	/**
	 * PAYROLL
	 * Get No of Entries
	**/
	public function getCount($param)
	{
		$param = (is_array($param))? $param: array($param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'count' => new Expression('COUNT(id)')
		));
		$select->where($param);
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		$columns = null; // Initialize to avoid undefined variable warning
		foreach ($results as $result):
			$column =  $result['count'];
		endforeach;
		return $column;
	}
	/**
	 * PAYROLL
	 * Get Sum of a column
	**/
	public function getSum($param,$column)
	{
		$param = (is_array($param))? $param: array($param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'sum' => new Expression('SUM('.$column.')')
		));
		$select->where($param);
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		$columns = null; // Initialize to avoid undefined variable warning
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
		return $column;
	}
/**
	 * CONTROL SUMMARY
	 * Get Sum of Earning_lwpd & Deduction_lwpd
	**/
	public function getLWPD($param,$status)
	{
		$param = (is_array($param))? $param: array($param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('pr'=>$this->table));
		$select->columns(array(
				'earning_lwpd' => new Expression('SUM(pr.earning_dlwp)'),
				'deduction_lwpd' => new Expression('SUM(pr.deduction_dlwp)'),
		));
		$select->join(array('his'=>'hr_emp_history'), 'his.id=pr.emp_his', array())
				->where($param);
		if($status == 'R'):
			$select->where->NotequalTo('his.position_level','19');
		else:
			$select->where->equalTo('his.position_level','19');
		endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		$columns = null; // Initialize to avoid undefined variable warning
		foreach ($results as $result):
			$earning_lwpd =  $result['earning_lwpd'];
			$deduction_lwpd =  $result['deduction_lwpd'];
		endforeach;
		return $earning_lwpd+deduction_lwpd;
	}
}
