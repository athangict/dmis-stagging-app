<?php

/**
 * Global Configuration Override
 *
 * You can use this file for overriding configuration values from modules, etc.
 * You would place values in here that are agnostic to the environment and not
 * sensitive to security.
 *
 * NOTE: In practice, this file will typically be INCLUDED in your source
 * control, so do not include passwords or other sensitive information in this
 * file.
 */
use Laminas\Db\Adapter\AdapterAbstractServiceFactory;
use Laminas\Session\Storage\SessionArrayStorage;
use Laminas\Session\Validator\RemoteAddr;
use Laminas\Session\Validator\HttpUserAgent;
    
return [
    'session_validators' => [
        RemoteAddr::class,
        HttpUserAgent::class,
    ],
    'session_config' => [
        'remember_me_seconds' => 604800, // one week
        'use_cookies' => true,
        'cookie_lifetime' => 604800, // one week
        'name' => 'BhutanPostSession',
    ],
    'session_storage' => [
        'type' => SessionArrayStorage::class,
    ],
    'nats' => [
        'host' => '13.229.203.54',
        'port' => 4222,
        'seed' => 'SUAPXY7TJFUFE3IX3OEMSLE3JFZJ3FZZRSRSOGSG2ANDIFN77O2MIBHWUM',
    ],
    'static_salt' => 'BhutanPost-MONALERP*BTN@2022(TPHU)#AICT',
    'ditt_api_census'    => "http://localhost/final_DCRC_API/",
];