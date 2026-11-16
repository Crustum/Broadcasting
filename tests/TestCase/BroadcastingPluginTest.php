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
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use JMac\Testing\Matching\Argument;

/**
 * BroadcastingPlugin Test
 *
 * Tests for the BroadcastingPlugin class.
 */
class BroadcastingPluginTest extends TestCase
{
    use VerifiesDoubles;

    /**
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
    protected function setUp(): void
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
    protected function tearDown(): void
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

        $app = Double::for(PluginApplicationInterface::class);
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

        $app = Double::for(PluginApplicationInterface::class);
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

        $app = Double::for(PluginApplicationInterface::class);
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

        $app = Double::for(PluginApplicationInterface::class);
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

        $app = Double::for(PluginApplicationInterface::class);
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
        $routes = Double::for(RouteBuilder::class);
        $routes->expects('plugin')
            ->with(
                'Crustum/Broadcasting',
                ['path' => '/broadcasting'],
                Argument::type('Closure'),
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
}
