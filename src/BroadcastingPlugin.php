<?php
declare(strict_types=1);

namespace Crustum\Broadcasting;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\PluginApplicationInterface;
use Cake\Log\Log;
use Cake\Routing\RouteBuilder;
use Crustum\Broadcasting\Command\ChannelCommand;
use Crustum\PluginManifest\Manifest\ManifestInterface;
use Crustum\PluginManifest\Manifest\ManifestTrait;

/**
 * Plugin for Broadcasting
 *
 * @uses \Crustum\PluginManifest\Manifest\ManifestTrait
 */
class BroadcastingPlugin extends BasePlugin implements ManifestInterface
{
    use ManifestTrait;

    /**
     * Dispatched before a broadcast is sent to a driver.
     *
     * Listeners may return an array via the event result to replace the payload
     * (for example to attach reserved `__crustum` correlation metadata).
     *
     * @var string
     */
    public const EVENT_BEFORE_SEND = 'Broadcasting.beforeSend';

    /**
     * Dispatched after a broadcast has been sent to a driver.
     *
     * @var string
     */
    public const EVENT_SENT = 'Broadcasting.sent';

    /**
     * Reserved payload key for cross-process debug metadata.
     *
     * Drivers / WebSocket servers (for example BlazeCast) must strip this key
     * before delivering payloads to clients. Broadcasting itself does not require Speculum.
     *
     * @var string
     */
    public const RESERVED_META_KEY = '__crustum';

    /**
     * Load all the plugin configuration and bootstrap logic.
     *
     * The host application is provided as an argument. This allows you to load
     * additional plugin dependencies, or attach events.
     *
     * @param \Cake\Core\PluginApplicationInterface $app The host application
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        if (Configure::read('Broadcasting') === null) {
            Configure::load('Crustum/Broadcasting.broadcasting', 'default', false);
        }

        $broadcastingConfig = Configure::read('Broadcasting.connections');
        if ($broadcastingConfig && is_array($broadcastingConfig)) {
            Broadcasting::initFromConfigure($broadcastingConfig);
        }

        Broadcasting::routes();

        $logConfig = Configure::read('Broadcasting.log');
        if (!empty($logConfig['enabled'])) {
            $logFile = $logConfig['file'] ?? 'broadcasting';
            Log::setConfig('broadcasting', [
                'className' => 'File',
                'path' => LOGS,
                'file' => $logFile,
                'scopes' => ['broadcasting'],
                'levels' => ['info'],
            ]);
        }
    }

    /**
     * Add console commands for the plugin.
     *
     * @param \Cake\Console\CommandCollection $commands The command collection to update
     * @return \Cake\Console\CommandCollection
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands = parent::console($commands);
        $commands->add('bake channel', ChannelCommand::class);

        return $commands;
    }

    /**
     * Add routes for the plugin.
     *
     * Registers `/broadcasting/auth` and `/broadcasting/user-auth`.
     * When the host application enables `CsrfProtectionMiddleware` globally,
     * those actions must be excluded via `skipCheckCallback` or by not applying
     * CSRF to this plugin scope. See docs/index.md#csrf-and-channel-authorization.
     *
     * If your plugin has many routes and you would like to isolate them into a separate file,
     * you can create `$plugin/config/routes.php` and delete this method.
     *
     * @param \Cake\Routing\RouteBuilder $routes The route builder to update.
     * @return void
     */
    public function routes(RouteBuilder $routes): void
    {
        $routes->plugin(
            'Crustum/Broadcasting',
            ['path' => '/broadcasting'],
            function (RouteBuilder $builder): void {

                $builder->connect('/auth', [
                'prefix' => null,
                'plugin' => 'Crustum/Broadcasting',
                'controller' => 'BroadcastingAuth',
                'action' => 'auth',
                ]);
                $builder->connect('/user-auth', [
                'prefix' => null,
                'plugin' => 'Crustum/Broadcasting',
                'controller' => 'BroadcastingAuth',
                'action' => 'userAuth',
                ]);

                $builder->fallbacks();
            },
        );

        parent::routes($routes);
    }

    /**
     * Get the manifest for the plugin.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function manifest(): array
    {
        $pluginPath = dirname(__DIR__);

        return array_merge(
            static::manifestConfig(
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'broadcasting.php',
                CONFIG . 'broadcasting.php',
                false,
            ),
            static::manifestConfig(
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'channels.php.example',
                CONFIG . 'channels.php',
                false,
            ),
            static::manifestBootstrapAppend(
                "if (file_exists(CONFIG . 'broadcasting.php')) {\n    Configure::load('broadcasting', 'default');\n}",
                '// Broadcasting Plugin Configuration',
            ),
            static::manifestStarRepo('Crustum/Broadcasting'),
        );
    }
}
