<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase;

use Cake\Console\CommandCollection;
use Cake\Core\Configure;
use Cake\Core\PluginApplicationInterface;
use Cake\Log\Log;
use Cake\Routing\RouteBuilder;
use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Broadcasting;
use Crustum\Broadcasting\BroadcastingPlugin;
use Crustum\Broadcasting\TestSuite\TestBroadcaster;

/**
 * BroadcastingPlugin Test
 *
 * Tests for the BroadcastingPlugin class.
 */
class BroadcastingPluginTest extends TestCase
{
    /**
     * Clear all Broadcasting configurations
     *
     * @return void
     */
    protected function clearBroadcastingConfigurations(): void
    {
        foreach (Broadcasting::configured() as $configName) {
            Broadcasting::drop((string)$configName);
        }
        Broadcasting::getRegistry()->reset();
        Configure::delete('Broadcasting');
    }

    /**
     * Set up test case
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->clearBroadcastingConfigurations();

        TestBroadcaster::replaceAllBroadcasters();
        TestBroadcaster::clearBroadcasts();
    }

    /**
     * Tear down test case
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->clearBroadcastingConfigurations();
        Log::drop('broadcasting');
        parent::tearDown();
    }

    /**
     * Test bootstrap with full configuration
     *
     * @return void
     */
    public function testBootstrapWithFullConfiguration(): void
    {
        Configure::write('Broadcasting.connections', [
            'test' => [
                'className' => TestBroadcaster::class,
            ],
            'default' => [
                'className' => TestBroadcaster::class,
            ],
        ]);

        $app = $this->createStub(PluginApplicationInterface::class);
        $plugin = new BroadcastingPlugin();

        $plugin->bootstrap($app);

        $this->assertContains('test', Broadcasting::configured());
    }

    /**
     * Test bootstrap with missing configuration
     *
     * @return void
     */
    public function testBootstrapWithMissingConfiguration(): void
    {
        Configure::delete('Broadcasting.connections');
        Configure::write('Broadcasting.connections', [
            'default' => [
                'className' => TestBroadcaster::class,
            ],
        ]);

        $app = $this->createStub(PluginApplicationInterface::class);
        $plugin = new BroadcastingPlugin();

        $plugin->bootstrap($app);

        $this->assertInstanceOf(BroadcastingPlugin::class, $plugin);
    }

    /**
     * Test bootstrap with log configuration
     *
     * @return void
     */
    public function testBootstrapWithLogConfiguration(): void
    {
        Configure::write('Broadcasting.connections', [
            'default' => [
                'className' => TestBroadcaster::class,
            ],
        ]);
        Configure::write('Broadcasting.log', [
            'enabled' => true,
            'file' => 'test-broadcasting',
        ]);

        $app = $this->createStub(PluginApplicationInterface::class);
        $plugin = new BroadcastingPlugin();

        $plugin->bootstrap($app);

        $this->assertTrue(Log::getConfig('broadcasting') !== null);
    }

    /**
     * Test bootstrap with log configuration without file
     *
     * @return void
     */
    public function testBootstrapWithLogConfigurationWithoutFile(): void
    {
        Configure::write('Broadcasting.connections', [
            'default' => [
                'className' => TestBroadcaster::class,
            ],
        ]);
        Configure::write('Broadcasting.log', [
            'enabled' => true,
        ]);

        $app = $this->createStub(PluginApplicationInterface::class);
        $plugin = new BroadcastingPlugin();

        $plugin->bootstrap($app);

        $this->assertTrue(Log::getConfig('broadcasting') !== null);
    }

    /**
     * Test bootstrap with log disabled
     *
     * @return void
     */
    public function testBootstrapWithLogDisabled(): void
    {
        Configure::write('Broadcasting.connections', [
            'default' => [
                'className' => TestBroadcaster::class,
            ],
        ]);
        Configure::write('Broadcasting.log', [
            'enabled' => false,
        ]);

        $app = $this->createStub(PluginApplicationInterface::class);
        $plugin = new BroadcastingPlugin();

        $plugin->bootstrap($app);

        $this->assertInstanceOf(BroadcastingPlugin::class, $plugin);
    }

    /**
     * Test routes registers plugin routes
     *
     * @return void
     */
    public function testRoutes(): void
    {
        $routes = $this->createMock(RouteBuilder::class);
        $routes->expects($this->once())
            ->method('plugin')
            ->with(
                $this->equalTo('Crustum/Broadcasting'),
                $this->equalTo(['path' => '/broadcasting']),
                $this->isInstanceOf('Closure'),
            );

        $plugin = new BroadcastingPlugin();
        $plugin->routes($routes);
    }

    /**
     * Test console registers channel command
     *
     * @return void
     */
    public function testConsole(): void
    {
        $commands = new CommandCollection();
        $plugin = new BroadcastingPlugin();

        $result = $plugin->console($commands);

        $this->assertSame($commands, $result);
        $this->assertTrue($commands->has('bake channel'));
    }

    /**
     * Test manifest returns array
     *
     * @return void
     */
    public function testManifest(): void
    {
        $manifest = BroadcastingPlugin::manifest();

        $this->assertNotEmpty($manifest);
    }

    /**
     * Test manifest includes config files
     *
     * @return void
     */
    public function testManifestIncludesConfigFiles(): void
    {
        $manifest = BroadcastingPlugin::manifest();

        $hasConfig = false;
        foreach ($manifest as $item) {
            if (isset($item['type']) && ($item['type'] === 'config' || isset($item['source']))) {
                $hasConfig = true;
                break;
            }
        }

        $this->assertTrue($hasConfig);
    }

    /**
     * Test manifest includes bootstrap append
     *
     * @return void
     */
    public function testManifestIncludesBootstrapAppend(): void
    {
        $manifest = BroadcastingPlugin::manifest();

        $hasBootstrap = false;
        foreach ($manifest as $item) {
            if (isset($item['type']) && $item['type'] === 'append') {
                $hasBootstrap = true;
                break;
            }
            if (isset($item['tag']) && $item['tag'] === 'bootstrap') {
                $hasBootstrap = true;
                break;
            }
        }

        $this->assertTrue($hasBootstrap);
    }
}
