<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Broadcasting\Broadcasting;

/**
 * Test Application Bootstrap
 *
 * This file is used to configure the test application for broadcasting tests.
 * It sets up the broadcasting configuration and loads necessary plugins.
 */

Configure::write('Broadcasting', [
    'channels_file' => __DIR__ . DS . 'channels.php',
    'default' => 'pusher',
    'connections' => [
        'null' => [
            'className' => 'Crustum/Broadcasting.Null',
        ],
        'log' => [
            'className' => 'Crustum/Broadcasting.Log',
        ],
        'pusher' => [
            'className' => 'Crustum/Broadcasting.Pusher',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'app_id' => 'test-app-id',
            'options' => [
                'cluster' => 'mt1',
                'useTLS' => false,
                'host' => 'localhost',
                'port' => 6001,
                'scheme' => 'http',
            ],
        ],
    ],
]);

Broadcasting::setConfig('log', [
    'className' => 'Crustum/Broadcasting.Log',
]);

Broadcasting::setConfig('null', [
    'className' => 'Crustum/Broadcasting.Null',
]);

Broadcasting::setConfig('default', [
    'className' => 'Crustum/Broadcasting.Log',
]);

Broadcasting::setConfig('pusher', [
    'className' => 'Crustum/Broadcasting.Log',
]);
