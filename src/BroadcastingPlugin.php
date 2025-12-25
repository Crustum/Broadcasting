<?php
declare(strict_types=1);

namespace Crustum\Broadcasting;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\PluginApplicationInterface;
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
    }

    /**
     * Add console commands for the plugin.
     *
     * @param \Cake\Console\CommandCollection $commands The command collection to update
     * @return \Cake\Console\CommandCollection
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands->add('bake channel', ChannelCommand::class);

        return $commands;
    }

    /**
     * Add routes for the plugin.
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
            'Broadcasting',
            ['path' => '/broadcasting'],
            function (RouteBuilder $builder): void {
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
