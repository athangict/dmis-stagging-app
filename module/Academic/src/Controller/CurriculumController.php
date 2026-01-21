<?php
namespace Academic\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Laminas\Mvc\MvcEvent;
use Interop\Container\ContainerInterface;
use Administration\Model as Administration;
use Acl\Model as Acl;
use Academic\Model as Academic;

class CurriculumController extends AbstractActionController
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
    protected $_highest_role;// highest user role
    protected $_lowest_role;// loweset user role
	protected $_safedataObj; // safedata controller plugin
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
		
		//$this->_permissionObj =  $this->PermissionPlugin();
		//$this->_permissionObj->permission($this->getEvent());	
	}
	/**
	 * index Action of MasterController
	 */
    public function indexAction()
    {  
    	$this->init(); 
		
        return new ViewModel([
			'title' => 'ClassRoom Menu',
		]);
	}
	/**
	 * Initiate academic action
	 */
	public function initiateacademicAction()
	{
		$this->init();
		    $id = $this->_id;
		
		$academicdetail = $this->getDefinedTable(Academic\CurriculumTable::class)->get(array('organization'=>$id));
		// print_r($academicdetail);
		// exit;
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($academicdetail));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(20);
		$paginator->setPageRange(8);
        return new ViewModel(array(
			'title'            => 'Academic Details',
			'paginator'        => $paginator,
			'page'             => $page,
			'academic_year'  => $this->getDefinedTable(Academic\StudentTable::class)->getDistinct('registration_year'),
			'batch'  => $this->getDefinedTable(Academic\StudentTable::class)->getDistinct('registration_year'),
			'districts'  => $this->getDefinedTable(Administration\DistrictTable::class)->getAll(),
			'classes'  => $this->getDefinedTable(Academic\ClassTable::class),
			'program'  => $this->getDefinedTable(Academic\ProgramTable::class),
			'organization'  => $this->getDefinedTable(Administration\LocationTable::class)->get(array('id'=>$id)),
			'org'  => $this->getDefinedTable(Administration\LocationTable::class),
		)); 
	}
	/**
	 * add Initiate academic details
	 */
    public function addinitiateacademicAction()
    {
		$this->init();
		$page = $this->_id;
		// print_r($page);
		// exit;
	
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
			$year = strtr($form['start_date'], '/', '-');
			$year = date('Y', strtotime($year));
			$program=$this->getDefinedTable(Academic\ProgramTable::class)->get(array('id'=>$form['program']));
			foreach($program as $level)
			// print_r($level);
			// exit;
			$data = array(
				'class_name'     =>$form['class_name'],
				'start_date'     =>$form['start_date'],
				'end_date'       =>$form['end_date'],
				'academic_year'  => $year,
				'organization'   => $page,
				'level'          => $level['level'],
				'batch'          => $year,
				'program'         =>$form['program'],
				'status'         =>1,
				'author'         => $this->_author,
				'created'        => $this->_created,
				'modified'       => $this->_modified
			);
			$result = $this->getDefinedTable(Academic\CurriculumTable::class)->save($data);
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully added new academic detail."); 	             
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new academic detail.");	 	             
			endif;
			return $this->redirect()->toRoute('curriculum/paginator', array('action' => 'initiateacademic', 'page'=>$this->_id, 'id'=>$page));
		}
		$ViewModel = new ViewModel([
			'title'        => 'Create Class',
			'page'         => $page,
			'academic_year'  => $this->getDefinedTable(Academic\StudentTable::class)->getDistinct('registration_year'),
			'batch'  => $this->getDefinedTable(Academic\StudentTable::class)->getDistinct('registration_year'),
			'districts'  => $this->getDefinedTable(Administration\DistrictTable::class)->getAll(),
			'courses'  => $this->getDefinedTable(Academic\CourseTable::class)->getAll(),
			'classes'  => $this->getDefinedTable(Academic\ClassTable::class)->getAll(),
			'classobj'  => $this->getDefinedTable(Academic\ClassTable::class),
			'curriculum'  => $this->getDefinedTable(Academic\CurriculumTable::class)->get(array('organization'=>$page)),
			'program'     =>$this->getDefinedTable(Academic\ProgramTable::class)->getAll(),
		]); 
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 *  edit Initiate academic details
	 **/
	public function editinitiateacademicAction()
	{
		$this->init();
		$id = $this->_id;
		$array_id = explode("_", $id);
		$academic_id = $array_id[0];
		$page = (sizeof($array_id) > 1) ? $array_id[1] : '';
		if ($this->getRequest()->isPost()) {
			$form = $this->getRequest()->getPost();
			$year = strtr($form['start_date'], '/', '-');
			$year = date('Y', strtotime($year));
			$data = array(
				'id' => $form['id'],
				'class_name'     =>$form['class_name'],
				'start_date'     =>$form['start_date'],
				'end_date'       =>$form['end_date'],
				'academic_year'  => $year,
				'level'          => $form['level'],
				'batch'          => $year,
				'capacity'       =>$form['capacity'],
				'course'         =>$form['course'],
				'status'         =>1,
				'author' => $this->_author,
				'created' => $this->_created,
				'modified' => $this->_modified
			);
			//print_r($data);exit;
			$result = $this->getDefinedTable(Academic\CurriculumTable::class)->save($data);

			if ($result):
				$this->flashMessenger()->addMessage("success^ Successfully edited academic detail.");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to edit lacademic detail.");
			endif;
			return $this->redirect()->toRoute('curriculum/paginator', array('action' => 'initiateacademic', 'page'=>$this->_id, 'id'=>$id));
		}
		$ViewModel = new ViewModel(
			array(
				'title' => 'Edit Class detail',
				'page' => $page,
				'academic' => $this->getDefinedTable(Academic\CurriculumTable::class)->get($this->_id),
				'organization' => $this->getDefinedTable(Administration\LocationTable::class)->get(array('status' => 1)),
				'classes' => $this->getDefinedTable(Academic\ClassTable::class)->get(array('status' => 1)),
				'course'=>$this->getDefinedTable(Academic\CourseTable::class)->get(array('status' => 1)),
			)
		);
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 *View Initiate academic action
	 */
	public function viewcurriculumAction()
	{
		$this->init();
		$organization = $this->getDefinedTable(Administration\LocationTable::class)->getAll();
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($organization));
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(25);
		$paginator->setPageRange(8);
        return new ViewModel(array(
			'title'            => 'Organization List',
			'paginator'        => $paginator,
			'page'             => $page,
			'regionObj'       => $this->getDefinedTable(Administration\RegionTable::class),
			'locationtypeObj' => $this->getDefinedTable(Administration\LocationTypeTable::class),
			'districtObj'     => $this->getDefinedTable(Administration\DistrictTable::class),
			'locationObj'     => $this->getDefinedTable(Administration\LocationTable::class),
			'orglevel'        => $this->getDefinedTable(Administration\OrganizationlevelTable::class),
		)); 
		
	}
	/**
	 * Student List action
	 */
	public function studentlistAction()
	{
		$this->init();
		$academic = $this->getDefinedTable(Academic\CurriculumTable::class)->get($this->_id);
$academics = null; // Initialize to avoid undefined variable warning
		foreach($academic as $row):
			$academics = $row;
		endforeach;
		$studentlist = $this->getDefinedTable(Academic\ClassStudentTable::class)->get(array('aca_curriculum_id'=>$academics['id']));
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($studentlist));
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(20);
		$paginator->setPageRange(8);
        return new ViewModel(array(
			'title'            => 'Student List',
			'paginator'        => $paginator,
			'page'             => $page,
			'student'  => $this->getDefinedTable(Academic\StudentTable::class),
			'academic' => $academic,
			'organization'  => $this->getDefinedTable(Administration\LocationTable::class),
			'class'  => $this->getDefinedTable(Academic\ClassTable::class),
			'program'  => $this->getDefinedTable(Academic\ProgramTable::class),
		)); 
	}
	/**
	 * add student list
	 */
    public function addstudentlistAction()
    {
		$this->init();
		$page = $this->_id;
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
				$data = array(
					'aca_curriculum_id'  => $form['id'],
					'monk_id'       => $form['student'],
					'academic_year' => $form['academic_year'],
					'batch'         => $form['batch'],
					'organization'  => $form['organization'],
					'class'         => $form['class'],
					'course'      	=> $form['course'],
					'program'      	=> $form['program'],
					'author'        => $this->_author,
					'created'       => $this->_created,
					'modified'      => $this->_modified
				);
				//print_r($data);exit;
				$result = $this->getDefinedTable(Academic\ClassStudentTable::class)->save($data);
				if($result):
					$this->flashMessenger()->addMessage("success^ Successfully added a student to this curriculum."); 	             
				else:
					$this->flashMessenger()->addMessage("error^ Failed to add  student to this curriculum.");	 	             
				endif;
				return $this->redirect()->toRoute('curriculum/paginator', array('action' => 'studentlist', 'page'=>$this->_id, 'id'=>$form['id']));
			}
		$ViewModel = new ViewModel([
			'title'        => 'Result',
			'page'         => $page,
			'studentlist' =>$this->getDefinedTable(Academic\CurriculumTable::class)->get($this->_id),
			'student'  => $this->getDefinedTable(Academic\StudentTable::class),
			'class'  => $this->getDefinedTable(Academic\ClassTable::class)->getAll(),
			
		]); 
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * edit student list
	 */
    public function editstudentlistAction()
    {
		$this->init();
		$page = $this->_id;
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
			$data = array(
				'id'            => $form['id'],
				'aca_curriculum_id'=>$form['aca_curriculum_id'],
				'monk_id'       => $form['student'],
				'author'        => $this->_author,
				'created'       => $this->_created,
				'modified'      => $this->_modified
			);
			$result = $this->getDefinedTable(Academic\ClassStudentTable::class)->save($data);
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully added a student to this curriculum."); 	             
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add  student to this curriculum.");	 	             
			endif;
			return $this->redirect()->toRoute('curriculum/paginator', array('action' => 'studentlist', 'page'=>$this->_id, 'id'=>$form['aca_curriculum_id']));
		}
		$ViewModel = new ViewModel([
			'title'        => 'Edit Student',
			'page'         => $page,
			'classStudent' =>$this->getDefinedTable(Academic\ClassStudentTable::class)->get($this->_id),
			'student'  => $this->getDefinedTable(Academic\StudentTable::class),
			
		]); 
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * class Teacher  List action
	 */
	public function classteacherlistAction()
	{
		$this->init();
		$academic = $this->getDefinedTable(Academic\CurriculumTable::class)->get($this->_id);
$academics = null; // Initialize to avoid undefined variable warning
		foreach($academic as $row):
			$academics = $row;
		endforeach;
		$classteacherlist = $this->getDefinedTable(Academic\ClassTeacherTable::class)->get(array('aca_curriculum_id'=>$academics['id']));
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($classteacherlist));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(20);
		$paginator->setPageRange(8);
        return new ViewModel(array(
			'title'            => 'Class taecher List',
			'paginator'        => $paginator,
			'page'             => $page,
			'student'  => $this->getDefinedTable(Academic\StudentTable::class),
			'academic' => $academic,
			'organization'  => $this->getDefinedTable(Administration\LocationTable::class),
			'class'  => $this->getDefinedTable(Academic\ClassTable::class),
			'program'  => $this->getDefinedTable(Academic\ProgramTable::class),
		)); 
	}
	/**
	 * add class teacher list
	 */
    public function addclassteacherlistAction()
    {
		$this->init();
		$page = $this->_id;
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
			$data = array(
				'aca_curriculum_id'  => $form['id'],
				'monk_id'       => $form['student'],
				'academic_year' => $form['academic_year'],
				'batch'         => $form['batch'],
				'organization'  => $form['organization'],
				'class'         => $form['class'],
				'course'      	=> $form['course'],
				'program'      	=> $form['program'],
				'author'        => $this->_author,
				'created'       => $this->_created,
				'modified'      => $this->_modified
			);
			$result = $this->getDefinedTable(Academic\ClassTeacherTable::class)->save($data);
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully added a classteacher to this curriculum."); 	             
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add  classteacher to this curriculum.");	 	             
			endif;
			return $this->redirect()->toRoute('curriculum/paginator', array('action' => 'classteacherlist', 'page'=>$this->_id, 'id'=>$form['id']));
		}
		$ViewModel = new ViewModel([
			'title'        => 'Add Class Teacher',
			'page'         => $page,
			'studentlist' =>$this->getDefinedTable(Academic\CurriculumTable::class)->get($this->_id),
			'student'  => $this->getDefinedTable(Academic\StudentTable::class),
			'class'  => $this->getDefinedTable(Academic\ClassTable::class)->getAll(),
			
		]); 
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * edit class teacher list
	 */
    public function editclassteacherlistAction()
    {
		$this->init();
		$page = $this->_id;
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
			$data = array(
				'id'                  =>$form['id'],
				'aca_curriculum_id'  => $form['aca_curriculum_id'],
				'monk_id'       => $form['student'],
				'author'              => $this->_author,
				'created'             => $this->_created,
				'modified'            => $this->_modified
			);
			$result = $this->getDefinedTable(Academic\ClassTeacherTable::class)->save($data);
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully added a classteacher to this curriculum."); 	             
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add  classteacher to this curriculum.");	 	             
			endif;
			return $this->redirect()->toRoute('curriculum/paginator', array('action' => 'classteacherlist', 'page'=>$this->_id, 'id'=>$form['aca_curriculum_id']));
		}
		$ViewModel = new ViewModel([
			'title'        => 'Edit Class Teacher',
			'page'         => $page,
			'classTeacher' =>$this->getDefinedTable(Academic\ClassTeacherTable::class)->get($this->_id),
			'student'  => $this->getDefinedTable(Academic\StudentTable::class),
			
		]); 
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 *  Subject Teacher  assigned to subjects  action
	 */
	public function subjectteacherlistAction()
	{
		$this->init();
		$academic = $this->getDefinedTable(Academic\CurriculumTable::class)->get($this->_id);
$academics = null; // Initialize to avoid undefined variable warning
		foreach($academic as $row):
			$academics = $row;
		endforeach;
		$subjectteacherlist = $this->getDefinedTable(Academic\SubjectTeacherTable::class)->get(array('aca_clurriculum_id'=>$academics['id']));
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($subjectteacherlist));
		
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(20);
		$paginator->setPageRange(8);
        return new ViewModel(array(
			'title'            => 'Class taecher List',
			'paginator'        => $paginator,
			'page'             => $page,
			'student'  => $this->getDefinedTable(Academic\StudentTable::class),
			'subject'  => $this->getDefinedTable(Academic\SubjectTable::class),
			'academic' => $academic,
			'organization'  => $this->getDefinedTable(Administration\LocationTable::class),
			'class'  => $this->getDefinedTable(Academic\ClassTable::class),
			'program'  => $this->getDefinedTable(Academic\ProgramTable::class),
		)); 
	}
	/**
	 * add subjects and  teachers list
	 */
    public function addsubjectteacherlistAction()
    {
		$this->init();
		$page = $this->_id;
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
			$data = array(
				'aca_clurriculum_id'  => $form['id'],
				'monk_id'       => $form['staff'],
				'academic_year' => $form['academic_year'],
				'batch'         => $form['batch'],
				'organization'  => $form['organization'],
				'class'         => $form['class'],
				'course'      	=> $form['course'],
				'program'      	=> $form['program'],
				'subject'		=> $form['subject'],
				'author'        => $this->_author,
				'created'       => $this->_created,
				'modified'      => $this->_modified
			);
			$result = $this->getDefinedTable(Academic\SubjectTeacherTable::class)->save($data);
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully added Subject and Subject Teacher to this curriculum."); 	             
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add  Subject and Subject Teacher to this curriculum");	 	             
			endif;
			return $this->redirect()->toRoute('curriculum/paginator', array('action' => 'subjectteacherlist', 'page'=>$this->_id, 'id'=>$form['id']));
		}
		$ViewModel = new ViewModel([
			'title'        => 'Add Subject Teacher',
			'page'         => $page,
			'studentlist' =>$this->getDefinedTable(Academic\CurriculumTable::class)->get($this->_id),
			'student'  => $this->getDefinedTable(Academic\StudentTable::class),
			'subject'  => $this->getDefinedTable(Academic\SubjectTable::class),
			
		]); 
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
		/**
	 * edit subjects and  teachers list
	 */
    public function editsubjectteacherlistAction()
    {
		$this->init();
		$page = $this->_id;
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
			$data = array(
				'id'                  =>$form['id'],
				'aca_clurriculum_id'  => $form['aca_curriculum_id'],
				'monk_id'       => $form['student'],
				'author'        => $this->_author,
				'created'       => $this->_created,
				'modified'      => $this->_modified
			);
			$result = $this->getDefinedTable(Academic\SubjectTeacherTable::class)->save($data);
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully added Subject and Subject Teacher to this curriculum."); 	             
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add  Subject and Subject Teacher to this curriculum");	 	             
			endif;
			return $this->redirect()->toRoute('curriculum/paginator', array('action' => 'subjectteacherlist', 'page'=>$this->_id, 'id'=>$form['aca_curriculum_id']));
		}
		$ViewModel = new ViewModel([
			'title'        => 'Edit Subject Teacher',
			'page'         => $page,
			'subjectTeacher' =>$this->getDefinedTable(Academic\SubjectTeacherTable::class)->get($this->_id),
			'student'  => $this->getDefinedTable(Academic\StudentTable::class),
			'subject'  => $this->getDefinedTable(Academic\SubjectTable::class),
			
		]); 
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * editactivity Action
	 */
	public function editactivityAction()
	{
		$this->init();
		$id = $this->_id;
		$array_id = explode("_", $id);
		$activity_id = $array_id[0];
		$page = (sizeof($array_id)>1)?$array_id[1]:'';
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'id'             => $form['activity_id'],
				'activity'       => $form['activity'],
				'department'     => $form['department'],
				'prefix'         => $form['prefix'],
				'status'         => $form['status'],
				'author'         => $this->_author,
				'modified'       => $this->_modified
			);
			$result = $this->getDefinedTable(Administration\ActivityTable::class)->save($data);
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully edited activity."); 	             
			else:
				$this->flashMessenger()->addMessage("error^ Failed to edit activity.");	 	             
			endif;
			return $this->redirect()->toRoute('setmaster/paginator',array('action'=>'activity','page'=>$this->_id, 'id'=>$form['department']));
		}
		$ViewModel = new ViewModel(array(
				'title'         => 'Edit Activity',
				'page'          => $page,
				'departments'   => $this->getDefinedTable(Administration\DepartmentTable::class)->get(array('status'=>'1')),
				'activities'    => $this->getDefinedTable(Administration\ActivityTable::class)->get($activity_id),
		));		 
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * Get section
	 */
	public function getsectionAction()
	{
			
		$form = $this->getRequest()->getPost();
		$classId =$form['classId'];
		$sec = $this->getDefinedTable(Academic\SectionTable::class)->get(array('class'=>$classId));
		$section = "<option value='-1'>None</option>";
		foreach($sec as $secs):
			$section.="<option value='".$secs['id']."'>".$secs['section']."</option>";
		endforeach;
		echo json_encode(array(
				'section' => $section,
		));
		exit;
	}
	public function resultAction()
	{
		$this->init();
		$id=$this->_id;
		// print_r($id);
		// exit;
		return new ViewModel( array(
			'title' => "Result",
			'studentlist' => $this->getDefinedTable(Academic\ClassStudentTable::class)->get(array('monk_id'=>$id)),
			'result' => $this->getDefinedTable(Academic\ResultTable::class)->get(array('monk_id'=>$id)),
			'resultdetails' => $this->getDefinedTable(Academic\ResultDetailsTable::class),
			'subject'=>$this->getDefinedTable(Academic\SubjectTable::class),
			'classes' => $this->getDefinedTable(Academic\ClassTable::class),
			'student' => $this->getDefinedTable(Academic\StudentTable::class),
			'organization' => $this->getDefinedTable(Administration\DepartmentTable::class),
		));
	}
}
