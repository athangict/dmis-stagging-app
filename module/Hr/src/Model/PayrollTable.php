<?php
namespace Hr\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class PayrollTable extends AbstractTableGateway 
{
	protected $table = 'hr_payroll'; //tablename

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
				->join(array('emp'=>'hr_employee'), 'pr.employee = emp.id', array('employee_id'=>'id','full_name', 'emp_id'))
				->join(array('his'=>'hr_emp_history'), 'his.id=pr.emp_his', array())
				->join(array('t'=>'hr_employee_type'), 'his.employee_type = t.id', array('emp_type_id'=>'id','emp_type', 'type_code'=>'code'))
				->join(array('l'=>'adm_location'), 'his.location = l.id', array('location_id'=>'id','location'))
				->join(array('r'=>'adm_region'),'r.id = l.region', array('region', 'region_id' => 'id'))
				->order(array('his.position_level ASC'));
	    
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
				->join(array('emp'=>'hr_employee'), 'pr.employee = emp.id', array('employee_id'=>'id','full_name', 'emp_id'))
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
				->join(array('v'=>'adm_village'), 'v.id = e.village', array('village', 'village_id' => 'id'))
	            ->join(array('g'=>'adm_block'), 'g.id = v.block', array('block', 'gewog_id' => 'id'))
	            ->join(array('dz'=>'adm_district'), 'dz.id = g.district', array('district', 'dzongkhag_id' => 'id'))
		        ->join(array('his'=>'hr_emp_history'), 'his.id=pr.emp_his', array())
		        ->join(array('d'=>'sys_department'), 'd.id=his.department', array('department_id'=>'id','department'))
		        ->join(array('pt'=>'hr_post_title'), 'pt.id=his.position_title', array('position_title_id'=>'id','position_title' => 'position_title'))
		        ->join(array('pl'=>'hr_post_level'), 'pl.id=his.position_level', array('position_level_id'=>'id','position_level' => 'position_level','code'))
		        ->join(array('l'=>'adm_location'), 'l.id=his.location', array('location_id'=>'id','location'))
		        ->join(array('r'=>'adm_region'), 'r.id=l.region', array('region_id'=> 'id','region'))
	    		->join(array('act'=>'adm_activity'), 'act.id=his.activity', array('activity_id'=> 'id','activity'))
	    		->join(array('et'=>'hr_employee_type'), 'et.id=his.employee_type', array('emp_type_id'=> 'id','type'=>'emp_type', 'type_code'=>'code'))
		        ->where($where)
		        ->order(array('his.position_level ASC'));
		        
		$selectString = $sql->getSqlStringForSqlObject($select);
               //echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	 /**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getIndividualPaySlip($param)
	{
		//print_r($param); exit;
		$where = ( is_array($param) )? $param: array('pr.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('pr'=>$this->table))
				->join(array('emp'=>'hr_employee'), 'pr.employee = emp.id', array('employee_id'=>'id','full_name', 'emp_id'))
				->join(array('his'=>'hr_emp_history'), 'his.id=pr.emp_his', array())
				->join(array('t'=>'hr_employee_type'), 'his.employee_type = t.id', array('emp_type_id'=>'id','emp_type', 'type_code'=>'code'))
				->join(array('l'=>'adm_location'), 'his.location = l.id', array('location_id'=>'id','location'))
				->join(array('r'=>'adm_region'),'r.id = l.region', array('region', 'region_id' => 'id'))
		       ->where($where)
		       ->order(array('his.position_level ASC'));
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
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
	 * Return records of given condition array
	 * @param Int $column
	 * @param Int $param
	 * @return Array
	 */
	public function getMaxRow($column,$param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		
		$sub0 = new Select($this->table);
		$sub0->columns(array(
				$column => new Expression('MAX('.$column.')')
		));
		//$sub0 = $sub0->toArray();
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
				->where($where)
				->where($column);
	
		$selectString = $sql->getSqlStringForSqlObject($select);
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
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
	
		$sub0 = new Select($this->table);
		$sub0->columns(array(
				$column => new Expression('MIN('.$column.')')
		));
		//$sub0 = $sub0->toArray();
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
				->where($where)
				->where($column);
	
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * MONTHLY SALARY BOOKING
	 * Get Locations
	**/
	public function salaryBookingLocation($param)
	{
		$param = (is_array($param))? $param: array($param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('pr'=>$this->table))
				->columns(array())
				->join(array('his'=>'hr_emp_history'), 'his.id=pr.emp_his', array('location_id' => new Expression('Distinct(his.location)')))
				->join(array('l'=>'adm_location'), 'l.id=his.location', array('location'))
				->where($param);
				
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}

	/**
	 * MONTHLY SALARY BOOKING
	 * Get Activity
	**/
	public function salaryBookingActivity($location)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('pr'=>$this->table))
				->columns(array())
				->join(array('his'=>'hr_emp_history'), 'his.id=pr.emp_his', array('activity_id' => new Expression('Distinct(his.activity)')))
				->join(array('act'=>'adm_activity'), 'act.id=his.activity', array('activity'))
				->where(array('his.location'=> $location));
				
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * MONTHLY SALARY BOOKING
	 * Get Subheads
	**/
	public function salaryBookingSubhead($data)
	{
		extract($data);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$sub0 = new Select(array('pd'=>'hr_pay_details'));
		$sub0->columns(array());
		$sub0->join(array('pr'=>$this->table), 'pr.id=pd.pay_roll', array())
			 ->join(array('ph'=>'hr_pay_heads'), 'ph.id=pd.pay_head', array('head_type' => new Expression('Distinct(ph.payhead_type)')))
			 ->join(array('his'=>'hr_emp_history'), 'his.id=pr.emp_his', array())
			 ->where(array('pr.year'=>$year, 'pr.month'=>$month));
			 if($location != '-1'){
				$sub0->where(array('his.location'=> $location));
			 }
			 if($activity != '-1'){
				$sub0->where(array('his.activity'=> $activity));
			 }
		$select = $sql->select();
		$select->from(array('sh'=>'fa_sub_head'))
				->join(array('h'=>'fa_head'),'sh.head=h.id',array('head_id'=>'id', 'head'=>'code'))
				->join(array('pht'=>'hr_pay_head_type'),'sh.ref_id=pht.id',array('payhead_type_id'=>'id', 'deduction'))
				->where(array('sh.type'=>5,'pht.deduction'=>$deduction))
				->where->in('sh.ref_id',$sub0)
				->where->notin('sh.ref_id',array('12'));
				
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * MONTHLY SALARY BOOKING (AdvanceSalary)
	 * Get Subheads
	**/
	public function salaryAdvanceSubhead($data)
	{
		extract($data);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$sub0 = new Select(array('pd'=>'hr_pay_details'));
		$sub0->columns(array());
		$sub0->join(array('pr'=>$this->table), 'pr.id=pd.pay_roll', array('employee'))
			->join(array('his'=>'hr_emp_history'), 'his.id=pr.emp_his', array())
			 ->where(array('pr.year'=>$year, 'pr.month'=>$month,'pd.pay_head'=>12))
			 ->where->greaterThan('pd.amount',0);
			 if($location != '-1'){
				$sub0->where(array('his.location'=> $location));
			 }
			 if($activity != '-1'){
				$sub0->where(array('his.activity'=> $activity));
			 }
		$select = $sql->select();
		$select->from(array('sh'=>'fa_sub_head'))
		        ->join(array('h'=>'fa_head'),'sh.head=h.id',array('head_id'=>'id', 'head'=>'code'))
		        ->where(array('head' => 219,'type'=>8))
				->where->in('ref_id',$sub0);
				
				
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * GET EMPLOYEES WITH ADVANCE SALARY BOOKING
	 * Get Subheads
	**/
	public function getSADEmp($data)
	{
		extract($data);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('pd'=>'hr_pay_details'),array())
			->join(array('pr'=>$this->table), 'pr.id=pd.pay_roll', array('employee'))
			->join(array('his'=>'hr_emp_history'), 'his.id=pr.emp_his', array())
			 ->where(array('pr.year'=>$year, 'pr.month'=>$month,'pd.pay_head'=>12))
			 ->order(array('his.location ASC','his.activity ASC'))
			 ->where->greaterThan('pd.amount',0);
				
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * MONTHLY SALARY BOOKING
	 * Return payhead amount for payroll summary
	 * @param Array $param
	 * @return Array
	**/
	public function getAmtforSummary($param, $column)
	{
		extract($param);	
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('pr' => $this->table));
		$select->columns(array('amount' => new Expression('SUM(pr.'.$column.')')))
			->join(array('his'=>'hr_emp_history'), 'his.id=pr.emp_his', array())
			->join(array('l'=>'adm_location'), 'his.location = l.id', array())
			->join(array('r'=>'adm_region'),'r.id = l.region', array())
			->join(array('e' => 'hr_employee'), 'e.id = pr.employee', array());
		$select->where(array('pr.year' => $year, 'pr.month' => $month));
		if($activity != -1):
			$select->where(array('his.activity' => $activity));
		endif;
	
		if($department != -1):
			if($location != -1):
				$select->where(array('his.department'=>$department, 'his.location'=>$location));
			else:
				$select->where(array('his.department' => $department));
				if($region != -1):
					$select->where(array('l.region' => $region));
				endif;
			endif;
		else:
			if($location != -1):
				$select->where(array('his.location'=>$location));
			else:
				if($region != -1):
					$select->where(array('l.region'=>$region));
				endif;
			endif;
		endif;
	
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		$column = null; // Initialize to avoid undefined variable warning
		foreach($results as $result):
			$column = $result['amount'];
		endforeach;
		return $column;
	}
	
	/**
	 * ADVANCE SALARY DEDUCTION
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getSumYearMonth($param,$initial_date)
	{
		$where = ( is_array($param) )? $param: array('pr.id' => $param);
		$initial_y = date_format($initial_date, 'Y');
		$initial_m = date_format($initial_date, 'm');
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('pr'=>$this->table))
			   ->join(array('pd'=>'hr_pay_details'), 'pr.id = pd.pay_roll', array('pay_head'))
		       ->columns(array('sum' => new Expression("SUM(`amount`)")))
		       ->where($where);
		$select->where->greaterThanorEqualTo('pr.year',$initial_y);
		$select->where->greaterThanorEqualTo('pr.month',$initial_m);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		$columns = null; // Initialize to avoid undefined variable warning
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
		//echo $column; exit;
		return $column;
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
				->where($param);
				
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
