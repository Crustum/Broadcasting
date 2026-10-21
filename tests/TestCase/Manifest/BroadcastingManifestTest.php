<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Manifest;

use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\BroadcastingPlugin;
use Crustum\Broadcasting\Manifest\BroadcastingManifest;
use Crustum\PluginManifest\Manifest\ManifestResolver;

/**
 * Covers the standalone manifest provider: identical operations to the
 * former plugin-class manifest, discoverable without the interface.
 */
class BroadcastingManifestTest extends TestCase
{
    /**
     * The provider emits config, bootstrap and star operations.
     *
     * @return void
     */
    public function testManifestOperations(): void
    {
        $ops = BroadcastingManifest::manifest();
        $tags = array_column($ops, 'tag');

        $this->assertContains('config', $tags);
        $this->assertContains('bootstrap', $tags);
        $this->assertContains('star-repo', $tags);
    }

    /**
     * The resolver finds the provider for the plugin class.
     *
     * @return void
     */
    public function testResolverFindsProvider(): void
    {
        $this->assertSame(
            BroadcastingManifest::class,
            ManifestResolver::providerClass(BroadcastingPlugin::class),
        );
        $this->assertSame(BroadcastingManifest::manifest(), ManifestResolver::manifestFor(BroadcastingPlugin::class));
    }

    /**
     * The plugin class itself carries no manifest contract.
     *
     * @return void
     */
    public function testPluginClassHasNoManifestContract(): void
    {
        $this->assertFalse(method_exists(BroadcastingPlugin::class, 'manifest'));
    }
}
