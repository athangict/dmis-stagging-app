<?php
/**
 * Helper -- TabsHelper
 * chophel@athang.com
 * 2023
 */
namespace Application\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Acl\Model\AclTable;
use Interop\Container\ContainerInterface;

class TabsHelper extends AbstractHelper
{	
	protected $aclTable;
	private $_container;
	 
	public function __construct(AclTable $aclTable, ContainerInterface $container)
	{
		$this->aclTable = $aclTable;
		$this->_container = $container;
	}
	
	public function __invoke($action=NULL,$id=NULL)
	{  
		$routeMatch = $this->_container->get('Application')->getMvcEvent()->getRouteMatch();
		$routeName = $routeMatch->getMatchedRouteName();
		$arr = explode('/', $routeName);
		$routeName = $arr[0];
		$routeAction = $routeMatch->getParam('action');	
		$routeParamID = $routeMatch->getParam('id');
		$routeResource = $this->aclTable->getColumn(array('route'=>$routeName),'resource');
		$acl_id = $this->aclTable->getColumn(array('route'=>$routeName, 'resource' => $routeResource, 'action'=>$routeAction),'id');
		$user_role= $this->view->identity()->role;	
		$highestRole = $this->aclTable->getHighestRole();
		if($action != NULL):
			$tabs ="";
			$tabs.= "<div class='mt-15'><ul class='nav nav-tabs nav-tabs-simple' id='tablist'>";
			for($i=0;$i<sizeof($action);$i++):
				$acl_permission = $this->aclTable->renderTabs(array('id' => $action[$i]), $user_role,$highestRole);
				if(sizeof($acl_permission)>0):
$row = null; // Initialize
					foreach($acl_permission as $temp_row):
						$row = $temp_row;
					endforeach;
					$class = ($row['route']== $routeName && $row['acl_id']==$acl_id)?'active':'';
					$tabs.="<li class='nav-item mr-1px".$class."'>
								<a class= 'd-style btn btn-outline-light btn-a-text-dark btn-a-outline-lightgrey bgc-white radius-0 py-2 text-95 ".$class."' title='".$row['menu']."' href='".$this->view->url($row['route'], array('action' => $row['action'], 'id'=> $id))."' role='tab'>
									<span class='v-active position-tl w-102 border-t-3 brc-danger mt-n3px ml-n1px'></span>
									<span class='v-n-active v-hover position-tl w-102 border-t-3 brc-success-tp3 mt-n2px ml-n1px'></span>
										<i class='".$row['icon']." text-success text-105 mr-3px'></i>
								".$row['menu']."</a></li>";
				endif;
			endfor;
			$tabs.="</ul></div>";
			return $tabs;
		endif;
	} 	
}
