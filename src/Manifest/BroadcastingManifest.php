<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Manifest;

use Crustum\Broadcasting\BroadcastingPlugin;
use Crustum\PluginManifest\Manifest\ManifestOps;

/**
 * Standalone manifest provider for the Broadcasting plugin.
 *
 * Plain class without interface or trait dependencies, so the plugin loads
 * and runs without `crustum/plugin-manifest` installed. Discovered by the
 * installer through the provider convention when the plugin class itself
 * provides no manifest. All package references stay inside method bodies,
 * which only execute when the installer (and therefore the package) exists.
 */
final class BroadcastingManifest
{
    /**
     * Plugin install assets via crustum/plugin-manifest.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function manifest(): array
    {
        $pluginPath = dirname(__DIR__, 2);
        $plugin = ManifestOps::pluginName(BroadcastingPlugin::class);

        return array_merge(
            ManifestOps::manifestConfig(
                $plugin,
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'broadcasting.php',
                CONFIG . 'broadcasting.php',
                false,
            ),
            ManifestOps::manifestConfig(
                $plugin,
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'channels.php.example',
                CONFIG . 'channels.php',
                false,
            ),
            ManifestOps::manifestBootstrapAppend(
                $plugin,
                "if (file_exists(CONFIG . 'broadcasting.php')) {\n    Configure::load('broadcasting', 'default');\n}",
                '// Broadcasting Plugin Configuration',
            ),
            ManifestOps::manifestStarRepo($plugin, 'Crustum/Broadcasting'),
        );
    }
}
