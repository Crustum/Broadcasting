<?php
declare(strict_types=1);

use Crustum\Broadcasting\Queue\CakeQueueAdapter;

/**
 * Broadcasting Plugin Configuration
 *
 * This file contains the default configuration for the Broadcasting plugin.
 * You can override these settings in your application's config/app.php file.
 */

return [
    'Broadcasting' => [
        /**
         * Default broadcasting driver
         *
         * The default driver to use for broadcasting events. This can be overridden
         * per connection or when creating broadcast events.
         */
        'default' => env('BROADCASTING_DRIVER', 'null'),

        'queue_adapter' => CakeQueueAdapter::class,

        /**
         * Channels file path
         *
         * Path to the file containing channel authorization callbacks.
         * If not set, defaults to CONFIG . 'channels.php'
         *
         * Example: CONFIG . 'channels.php'
         *          ROOT . DS . 'config' . DS . 'custom_channels.php'
         */
        'channels_file' => CONFIG . 'channels.php',

        /**
         * Broadcast logging configuration
         *
         * Configure logging of all broadcast messages to a separate file.
         * Useful for debugging and auditing.
         *
         * Example:
         * 'log' => [
         *     'enabled' => true,
         *     'file' => 'broadcasting',  // Logs to logs/broadcasting.log
         * ],
         */
        'log' => [
            'enabled' => env('BROADCASTING_LOG_ENABLED', false),
            'file' => env('BROADCASTING_LOG_FILE', 'broadcasting'),
        ],

        /**
         * Broadcasting connections
         *
         * Configure the broadcasting connections for your application. Each connection
         * represents a different broadcasting service or driver.
         */
        'connections' => [
            /**
             * Null driver for development and testing
             *
             * The null driver discards all broadcast events. This is useful for
             * development environments where you don't want to actually broadcast
             * events to external services.
             *
             * Supports multiple driver naming conventions:
             * - Full class name: 'Crustum\Broadcasting\Broadcaster\NullBroadcaster'
             * - Plugin-style: 'Crustum/Broadcasting.Null'
             */
            'null' => [
                'className' => 'Crustum/Broadcasting.Null',
            ],

            /**
             * Log driver for debugging
             *
             * The log driver writes broadcast events to the application log.
             * This is useful for debugging and development.
             */
            'log' => [
                'className' => 'Crustum/Broadcasting.Log',
            ],

            /**
             * Pusher driver for real-time broadcasting
             *
             * Pusher is a hosted service that provides real-time WebSocket
             * connections for broadcasting events to web applications.
             *
             * Example of using full class name instead of simple driver:
             * 'driver' => 'Crustum\Broadcasting\Broadcaster\PusherBroadcaster'
             * or plugin-style:
             * 'driver' => 'Crustum/Broadcasting.Pusher'
             */
            'pusher' => [
                'className' => 'Crustum/Broadcasting.Pusher',
                'key' => env('PUSHER_APP_KEY'),
                'secret' => env('PUSHER_APP_SECRET'),
                'app_id' => env('PUSHER_APP_ID'),
                'options' => [
                    'cluster' => env('PUSHER_APP_CLUSTER'),
                    'useTLS' => env('PUSHER_APP_USE_TLS', true),
                    'host' => env('PUSHER_HOST'),
                    'port' => env('PUSHER_PORT', 443),
                    'scheme' => env('PUSHER_SCHEME', 'https'),
                ],
            ],

            /**
             * Redis driver for self-hosted broadcasting
             *
             * Redis can be used as a broadcasting backend for self-hosted
             * WebSocket servers or other broadcasting services.
             */
            'redis' => [
                'className' => 'Crustum/Broadcasting.Redis',
                'connection' => env('BROADCASTING_REDIS_CONNECTION', 'default'),
            ],
        ],

        /**
         * Channel authorization callbacks
         *
         * Define authorization rules for private and presence channels.
         * These callbacks determine whether a user can access a channel.
         */
        'channels' => [
            // Example: Allow authenticated users to access private channels
            'private-*' => function ($user) {
                return $user !== null;
            },

            // Example: Allow authenticated users to access presence channels
            'presence-*' => function ($user) {
                return $user !== null;
            },
        ],

        /*
         * Queue Configuration
         *
         * Configuration for queue integration with CakePHP Queue plugin.
         * This allows broadcast events to be queued for background processing.
         */
        'queue' => [
            /*
             * Default queue name for broadcast events
             */
            'default_queue' => env('BROADCASTING_QUEUE', 'broadcasts'),

            /*
             * Default queue connection name
             */
            'default_connection' => env('BROADCASTING_QUEUE_CONNECTION', 'default'),

            /*
             * Maximum number of attempts for failed broadcast jobs
             */
            'max_attempts' => env('BROADCASTING_QUEUE_MAX_ATTEMPTS', 3),

            /*
             * Queue-specific configurations
             */
            'connections' => [
                'broadcasting' => [
                    'url' => env('BROADCASTING_QUEUE_URL', 'redis://localhost:6379/1'),
                    'queue' => env('BROADCASTING_QUEUE_NAME', 'broadcasts'),
                ],
            ],
        ],
    ],
];
