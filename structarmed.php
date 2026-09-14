<?php
declare(strict_types=1);

use Boundwize\StructArmed\Architecture;

return Architecture::define()
    ->layerPattern('Foundation', [
        '/^Crustum\\\\Broadcasting\\\\(Exception|Channel|Trait|Event|Support)(\\\\.*)?$/',
    ])
    ->layerPattern('Broadcaster', '/^Crustum\\\\Broadcasting\\\\Broadcaster(\\\\.*)?$/')
    ->layerPattern('Queue', '/^Crustum\\\\Broadcasting\\\\(Queue|Job)(\\\\.*)?$/')
    ->layerPattern('Registry', '/^Crustum\\\\Broadcasting\\\\Registry(\\\\.*)?$/')
    ->layerPattern('Model', '/^Crustum\\\\Broadcasting\\\\Model(\\\\.*)?$/')
    // Broadcasting facade is intentionally unregistered (Job/Model/Controller
    // call Broadcasting::*; unregistered classes are treated as external).
    // PendingBroadcast lives with Plugin — it resolves the plugin registry.
    ->layerPattern('Plugin', [
        '/^Crustum\\\\Broadcasting\\\\(BroadcastingPlugin|PendingBroadcast)$/',
        '/^Crustum\\\\Broadcasting\\\\(Command|Controller)(\\\\.*)?$/',
    ])
    ->layerPattern('TestSuite', '/^Crustum\\\\Broadcasting\\\\TestSuite(\\\\.*)?$/')
    ->ruleset([
        'Foundation' => [],
        'Broadcaster' => ['Foundation'],
        'Queue' => ['Foundation'],
        'Registry' => ['Broadcaster', 'Foundation'],
        'Model' => ['Foundation'],
        'Plugin' => ['Broadcaster', 'Registry', 'Model', 'Queue', 'Foundation'],
        'TestSuite' => ['+Plugin'],
    ]);
