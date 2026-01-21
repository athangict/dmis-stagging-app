<?php
namespace Auth;

use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;
use Laminas\Authentication\AuthenticationService;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Laminas\Session\SessionManager;
use Auth\Factory\SessionManagerFactory;
use Auth\Storage\AuthStorage;
use Auth\Factory\AuthenticationServiceFactory;
use Application\Factory\NatsServiceFactory;


return [
    'router' => [
        'routes' => [
            'auth' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/auth[/:action[/:id]]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => Controller\AuthController::class,
                        'action'     => 'index',
                    ],
                ],
            ],
            'webhook' => [
                'type'    => 'Literal',
                'options' => [
                    'route'    => '/webhook[/:action[/:id]]',
                    'defaults' => [
                        'controller' => Controller\AuthController::class,
                        'action'     => 'webhook',
                    ],
                ],
            ],
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'service_manager' => [
        'factories' => [
            AuthStorage::class => InvokableFactory::class,
            SessionManager::class => SessionManagerFactory::class,
            NatsService::class => NatsServiceFactory::class,
        ],
    ],
];
