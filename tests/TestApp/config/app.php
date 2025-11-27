<?php
/**
 * Test Application Configuration
 */

return [
    'debug' => true,
    'App' => [
        'namespace' => 'TestApp',
        'encoding' => 'UTF-8',
        'defaultLocale' => 'en_US',
        'defaultTimezone' => 'UTC',
        'base' => false,
        'dir' => 'src',
        'webroot' => 'webroot',
        'wwwRoot' => WWW_ROOT,
        'fullBaseUrl' => false,
        'imageBaseUrl' => 'img/',
        'jsBaseUrl' => 'js/',
        'cssBaseUrl' => 'css/',
        'paths' => [
            'plugins' => [ROOT . DS . 'plugins' . DS],
            'templates' => [ROOT . DS . 'templates' . DS],
            'locales' => [ROOT . DS . 'resources' . DS . 'locales' . DS],
        ],
    ],
    'Security' => [
        'salt' => 'test-salt-for-testing-only-change-in-production',
    ],
    'Asset' => [
        'cacheTime' => '+1 year',
    ],
];
