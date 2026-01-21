<?php
namespace Organization;

use Laminas\Router\Http\Segment;

return array(
    'router' => array(
        'routes' => array(
			'org' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'    => '/org[/:action[/:id]]',
					'constraints' => array(
								'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
								'id'     	 => '[a-zA-Z0-9_-]*',
							),
					'defaults' => array(
						'controller' => Controller\IndexController::class,
						'action'        => 'index',
					),
				),
			),
	
			'ef' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'    => '/ef[/:action[/:id]]',
					'constraints' => array(
								'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
								'id'     	 => '[a-zA-Z0-9_-]*',
							),
					'defaults' => array(
						'controller' => Controller\EquipfurnitureController::class,
						'action'        => 'equipment',
					),
				),
			),
			'fac' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'    => '/fac[/:action[/:id]]',
					'constraints' => array(
								'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
								'id'     	 => '[a-zA-Z0-9_-]*',
							),
					'defaults' => array(
						'controller' => Controller\FacilityController::class,
						'action'        => 'financial',
					),
				),
			),
			
		),
	),	
	'view_manager' => array(
        'template_path_stack' => array(
            'organization'=>__DIR__ . '/../view/',
        ),
		'display_exceptions' => true,
    ),
);