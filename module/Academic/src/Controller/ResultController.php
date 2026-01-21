<?php
namespace Academic\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Stdlib\ArrayObject;
use Laminas\Validator\File\Size;
use Laminas\Validator\File\Extension;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Accounts\Model As Accounts;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Academic\Model As Academic;
use Hr\Model As Hr;

class ResultController extends AbstractActionController
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
    protected $_safedataObj; //safedata controller plugin
    protected $_connection; //Transaction connection
    
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
		
		$this->_config = $this->_container->get('Config');
		
		$this->_user = $this->identity();		
		$this->_login_id = $this->_user->id;  
		$this->_login_role = $this->_user->role;  
		$this->_author = $this->_user->id;  
		
		$this->_id = $this->params()->fromRoute('id');
		
	    $this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');
		
		//$this->_dir =realpath($fileManagerDir);

		//$this->_safedataObj =  $this->SafeDataPlugin();
		
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

	}
	
	public function indexAction()
	{
		$this->init();
		
		return new ViewModel( array(
				'module' => "Academic menu",
		) );
	}
	/**
	 * Examination action
	 */
	public function examinationAction()
	{
		$this->init();
			$id = $this->_id;
			$array_id = explode("_", $id);
			$academic_year = $array_id[0];
			$organization = (sizeof($array_id)>1)?$array_id[1]:'1';
			
			if($this->getRequest()->isPost())
			{
				$form      			= $this->getRequest()->getPost();
				$academic_year  	 = $form['academic_year'];
				$organization        = $form['organization'];
			}else{
				$academic_year        = $academic_year;
				$organization  	    = $organization;
			}
			$data = array(
				'academic_year'  	=> $academic_year,
				'organization'      => $organization,
			);
		$examination = $this->getDefinedTable(Academic\CurriculumTable::class)->getRegisteredAcademic($data);
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($examination));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(20);
		$paginator->setPageRange(8);
        return new ViewModel(array(
			'title'            => 'Result',
			'paginator'        => $paginator,
			'page'             => $page,
			'data'      	    => $data,
			'classes' => $this->getDefinedTable(Academic\ClassTable::class),
			'academic_year'  => $this->getDefinedTable(Academic\StudentTable::class)->getDistinct('registration_year'),
			'batch'  => $this->getDefinedTable(Academic\StudentTable::class)->getDistinct('registration_year'),
			'districts'  => $this->getDefinedTable(Administration\DistrictTable::class)->getAll(),
			'class'  => $this->getDefinedTable(Academic\ClassTable::class)->getAll(),
			'org'  => $this->getDefinedTable(Administration\LocationTable::class),
			'section'  => $this->getDefinedTable(Academic\SectionTable::class),
		)); 
	}
	/**
	 * View Student List  action
	 */
	public function viewstudentlistAction()
	{
		$this->init();
		$exam = $this->getDefinedTable(Academic\CurriculumTable::class)->get($this->_id);
$exams = null; // Initialize to avoid undefined variable warning
		foreach($exam as $row):
			$exams = $row;
		endforeach;
		$students = $this->getDefinedTable(Academic\ClassStudentTable::class)->get(array('aca_curriculum_id'=>$exams['id']));
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($students));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(20);
		$paginator->setPageRange(8);
        return new ViewModel(array(
			'title'            => 'Student List',
			'paginator'        => $paginator,
			'page'             => $page,
			'classes' => $this->getDefinedTable(Academic\ClassTable::class),
			'stdname' => $this->getDefinedTable(Academic\StudentTable::class),
			'organization' => $this->getDefinedTable(Administration\LocationTable::class),
			'section'=> $this->getDefinedTable(Academic\SectionTable::class),
		)); 
	}
	/**
	 * add examination date action
	 */
	public function editexaminationAction()
	{
		$this->init();
		$id = $this->_id;
		$array_id = explode("_", $id);
		$page = (sizeof($array_id)>1)?$array_id[1]:'';
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'id'  => $form['id'],
				'examination_date'=> $form['exam_date'],
				'author'         => $this->_author,
				'created'        => $this->_created,
				'modified'       => $this->_modified
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Academic\CurriculumTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully added exam details");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add exam details");
			endif;
			return $this->redirect()->toRoute('result',array('action' => 'examination'));
		}
		$ViewModel = new ViewModel(array(
				'title'     => 'Add/Edit Exam Date',
				'page'      => $page,
				'curriculum' => $this->getDefinedTable(Academic\CurriculumTable::class)->get($this->_id),
				
			));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * result action
	 */
	public function resultAction()
	{
		$this->init();
		
		$curriculum =$this->getDefinedTable(Academic\ClassStudentTable::class)->get(array('id'=>$this->_id));
$curriculum = null; // Initialize to avoid undefined variable warning
		foreach($curriculum as $row):
			$curriculum = $row;
		endforeach;
		//  print_r($curriculum);
		//  exit;
		return new ViewModel( array(
			'title' => "Result",
			'class_id'=>$this->_id,
			'curriculum' =>$this->getDefinedTable(Academic\ClassStudentTable::class)->get(array('id'=>$this->_id)),
			'result' => $this->getDefinedTable(Academic\ResultTable::class)->get(array('aca_curriculum'=>$curriculum['aca_curriculum_id'])),
			'resultdetails' => $this->getDefinedTable(Academic\ResultDetailsTable::class),
			'subject'=>$this->getDefinedTable(Academic\SubjectTable::class),
			'classes' => $this->getDefinedTable(Academic\ClassTable::class),
			'monk' => $this->getDefinedTable(Academic\StudentTable::class)->get(array('id'=>$curriculum['monk_id'])),
			'student' => $this->getDefinedTable(Academic\StudentTable::class),
			'organization' => $this->getDefinedTable(Administration\LocationTable::class),
			'program' => $this->getDefinedTable(Academic\ProgramTable::class),
			'dzongkhag' => $this->getDefinedTable(Administration\DistrictTable::class),
			'gewog' => $this->getDefinedTable(Administration\BlockTable::class),
			'village' => $this->getDefinedTable(Administration\VillageTable::class),
		));
	}
	/**
	 * result action
	 */
	public function addresultAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			//$percentage=($form['scored_marks']/$form['total_marks'])*100;
			if($form['result_id'] > 0 ){
				$data = array(
					'id'	 => $form['result_id'],
					'class_student_id'=>$this->_id,
					'monk_id'	 => $form['monk_id'],
					'academic_year'	 => $form['academic_year'],
					'batch'		 => $form['batch'],
					'organization'	 => $form['organization'],
					'class'			 => $form['class'],
					'aca_curriculum'=>$form['aca_curriculum'],
					// 'scored_marks'		 => $form['scored_marks'],
					// 'percentage'		 => $form['percentage'],
					'author'         => $this->_author,
					'created'        => $this->_created,
					'modified'       => $this->_modified
				);
			}
				
			else{
				$data = array(
					'monk_id'	 => $form['monk_id'],
					'class_student_id'=>$this->_id,
					'academic_year'	 => $form['academic_year'],
					'batch'		 => $form['batch'],
					'organization'	 => $form['organization'],
					'aca_curriculum'=>$form['aca_curriculum'],
					'class'			 => $form['class'],
					'author'         => $this->_author,
					'created'        => $this->_created,
					'modified'       => $this->_modified
				);
			}
				$data = $this->_safedataObj->rteSafe($data);
				$result = $this->getDefinedTable(Academic\ResultTable::class)->save($data);
			//echo"<pre>"; print_r($form); exit;
				$detail_id = $form['detail_id'];
				$result_id = $result;
				$subject = $form['subject'];
				$marks =$form['marks'];
				for($i=0;$i<sizeof($subject);$i++){
					if($detail_id[$i] > 0 ){
						/* Update result details*/
						$data2 = array(
						'id'  => $detail_id[$i],
						'result_id'  =>$result_id,
						'subject'  =>$subject[$i],
						'marks'  => $marks[$i],
						'author' =>$this->_author,					
						'modified' =>$this->_modified,
						);
					}
					else{
						/* Insert result details*/
						$data2 = array(					
							'result_id'  =>$result_id,
							'subject'  =>$subject[$i],
							'marks'  => $marks[$i],
							'author' =>$this->_author,					
							'modified' =>$this->_modified,
						);					
					}
				$data2 = $this->_safedataObj->rteSafe($data2);
				$result2 = $this->getDefinedTable(Academic\ResultDetailsTable::class)->save($data2);
			if($result2 > 0):
					$this->flashMessenger()->addMessage("success^ Successfully added");
					else:
					$this->flashMessenger()->addMessage("Failed^ Failed to add");
					endif; 	
					$this->redirect()->toRoute('result', array('action' => 'result','id'=>$this->_id));				
			
		}
							
		endif; 
		return new ViewModel(array(
			'title' => "Result",
		));
	}
	/**
	 * Confirm Result action
	 */
	public function confirmresultAction()
	{
		$this->init();
		$id=$this->_id;
		// print_r($id);
		// exit;
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$scored_marks=$form['scored_marks'];
			$total_marks=$form['total_marks'];
			$percentage=($scored_marks/$total_marks)*100;
			// print_r($percentage);
			// exit;
			if($percentage<40):$pass=0;else:$pass=1;endif;
			$data = array(
				'id'	 		=> $form['result_id'],
				'scored_marks'	=> $scored_marks,
				'percentage'	=> $percentage,
				'status'	     => 4,
				'pass'	    	 =>$pass,
				'author'         => $this->_author,
				'created'        => $this->_created,
				'modified'       => $this->_modified
			);
			//echo"<pre>"; print_r($data); exit;
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Academic\ResultTable::class)->save($data);
		
				if($result2 > 0):
					$this->flashMessenger()->addMessage("success^ Successfully confirmed the results");
					else:
					$this->flashMessenger()->addMessage("Failed^ Failed to confirm the results");
					endif; 	
					$this->redirect()->toRoute('result', array('action' => 'result','id'=>$id));				
			
		endif; 
		$ViewModel = new ViewModel(array(
			'title'     => 'Confirm Result',
			'curriculum' => $this->getDefinedTable(Academic\ClassStudentTable::class)->get(array('id'=>$this->_id)),
			'result' => $this->getDefinedTable(Academic\ResultTable::class)->get(array('class_student_id'=>$this->_id)),
			'subject'=>$this->getDefinedTable(Academic\SubjectTable::class),
			'resultdetails' => $this->getDefinedTable(Academic\ResultDetailsTable::class),
		));
	$ViewModel->setTerminal(True);
	return $ViewModel;
	}
	/**
	 * add individual result action
	 */
	public function addindiresultAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'yoe'=>$form['yoe'],
					'std_id' => $form['name'],
					'organization' => $form['department'],
					'class'=>$form['classes'],
					'subjects' => $form['subject'],
					'marks'=>$form['marks'],
					'author' => $this->_author,
				    'created' => $this->_created,
				    'modified' => $this->_modified,
			);
			//echo '<pre>';print_r($data);exit;
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Academic\ResultTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add");
			endif;
			return $this->redirect()->toRoute('result',array('action' => 'view','id'=>$form['name']));
		}
		$ViewModel = new ViewModel(array(
				'results'=>$this->getDefinedTable(Academic\ResultTable::class)->get(array('std_id'=>$this->_id)),
				'departments'=>$this->getDefinedTable(Administration\DepartmentTable::class)->getAll(),
				'classes'=>$this->getDefinedTable(Academic\ClassTable::class)->getAll(),
				'subjects'=>$this->getDefinedTable(Academic\SubjectTable::class)->getAll(),
				'student'=>$this->getDefinedTable(Hr\EmployeeTable::class)->getTempEmp(),
			));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 *View Result action
	 **/
	public function viewAction()
	{
		$this->init();	
		return new ViewModel(array(
			'title' => 'view',
			'results'=>$this->getDefinedTable(Academic\ResultTable::class)->get(array('std_id'=>$this->_id)),
			'student'=>$this->getDefinedTable(Hr\EmployeeTable::class),
			'subject'=>$this->getDefinedTable(Academic\SubjectTable::class),
			//'results' => $this->getDefinedTable(Academic\ResultTable::class)->getAll()
			
		));
	}	
	/**
	 * edit result Action
	 **/
	public function editindiresultAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$std_id=$form['std_id'];
			//echo '<pre>';print_r($std_id);exit;
			$data = array(
					'id' => $this->_id,
					'subjects' => $form['subject'],
					'marks' => $form['marks'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Academic\ResultTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to update");
			endif;
			return $this->redirect()->toRoute('result',array('action' => 'view','id'=>$std_id));
		}
		$ViewModel = new ViewModel(array(
		        'title'=>'Edit Result',
				'results' => $this->getDefinedTable(Academic\ResultTable::class)->get($this->_id),
				'subjects' => $this->getDefinedTable(Academic\SubjectTable::class)->getAll(),
			));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
/***
***/
	public function getsubjectAction()
	{		
		$form = $this->getRequest()->getPost();
		$classId = $form['classId'];
		$sub = $this->getDefinedTable(Academic\SubjectTable::class)->get(array('class_id'=>$classId));
		$stu = $this->getDefinedTable(Academic\StudentTable::class)->getstd(array('f.course'=>$classId));
		
		$subject = "<option value=''></option>";
		foreach($sub as $subs):
			$subject.="<option value='".$subs['id']."'>".$subs['subject']."</option>";
		endforeach;
		$student = "<option value=''></option>";
		foreach($stu as $stus):
			$student.="<option value='".$stus['std_id']."'>".$this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($stus['std_id'],'full_name')."</option>";
		endforeach;
		//echo'<pre>';print_r($stus['std_id']);exit;
		echo json_encode(array(
				'subject' => $subject,
				'student' => $student,
		));
		exit;

	}
	/*****
	***/
	public function getclassAction()
	{		
		$form = $this->getRequest()->getPost();
		$orgId =$form['orgId'];
		$org = $this->getDefinedTable(Academic\ClassTable::class)->get(array('organization'=>$orgId));
		
		$class = "<option value=''></option>";
		foreach($org as $orgs):
			$class.="<option value='".$orgs['id']."'>".$orgs['class']."</option>";
		endforeach;
		
		//print_r($class);
		echo json_encode(array(
				'class' => $class,
		));
		exit;

	}
}
