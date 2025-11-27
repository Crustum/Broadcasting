<?php
/**
 * Routes configuration for TestApp
 *
 * This file defines the routes used during testing.
 */

use Cake\Routing\RouteBuilder;

return static function (RouteBuilder $routes): void {
    $routes->setRouteClass('DashedRoute');

    $routes->scope('/', function (RouteBuilder $builder): void {
        $builder->connect('/broadcasting/auth', [
            'plugin' => 'Crustum/Broadcasting',
            'controller' => 'BroadcastingAuth',
            'action' => 'auth',
        ]);

        $builder->connect('/broadcasting/user-auth', [
            'plugin' => 'Crustum/Broadcasting',
            'controller' => 'BroadcastingAuth',
            'action' => 'userAuth',
        ]);

        $builder->connect('/orders/create', ['controller' => 'Orders', 'action' => 'create']);
        $builder->connect('/orders/update', ['controller' => 'Orders', 'action' => 'update']);
        $builder->connect('/orders/broadcast-with-connection', ['controller' => 'Orders', 'action' => 'broadcastWithConnection']);

        $builder->fallbacks('DashedRoute');
    });
};
