<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Registry;

use BadMethodCallException;
use Cake\Core\Exception\CakeException;
use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Broadcaster\BroadcasterInterface;
use Crustum\Broadcasting\Broadcaster\LogBroadcaster;
use Crustum\Broadcasting\Broadcaster\NullBroadcaster;
use Crustum\Broadcasting\Registry\BroadcasterRegistry;

/**
 * Broadcaster Registry Test
 */
class BroadcasterRegistryTest extends TestCase
{
    /**
     * Broadcaster registry instance
     *
     * @var \Crustum\Broadcasting\Registry\BroadcasterRegistry
     */
    protected BroadcasterRegistry $registry;

    /**
     * Set up test case
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->registry = new BroadcasterRegistry();
    }

    /**
     * Tear down test case
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();

        $this->registry->reset();
    }

    /**
     * Test loading a broadcaster with full class name
     *
     * @return void
     */
    public function testLoadWithFullClassName(): void
    {
        $config = [
            'className' => NullBroadcaster::class,
            'key' => 'test-key',
        ];

        $broadcaster = $this->registry->load('test_null_fqcn', $config);

        $this->assertInstanceOf(NullBroadcaster::class, $broadcaster);
        $this->assertContains('test_null_fqcn', $this->registry->loaded());
    }

    /**
     * Test loading a broadcaster with plugin-style name
     *
     * @return void
     */
    public function testLoadWithPluginStyleName(): void
    {
        $config = [
            'className' => 'Crustum/Broadcasting.Null',
            'key' => 'test-key',
        ];

        $broadcaster = $this->registry->load('test_null_plugin', $config);

        $this->assertInstanceOf(NullBroadcaster::class, $broadcaster);
        $this->assertContains('test_null_plugin', $this->registry->loaded());
    }

    /**
     * Test loading a broadcaster with object instance
     *
     * @return void
     */
    public function testLoadWithObjectInstance(): void
    {
        $broadcasterInstance = new NullBroadcaster(['key' => 'test-key']);
        $config = [
            'className' => $broadcasterInstance,
        ];

        $broadcaster = $this->registry->load('test_null_object', $config);

        $this->assertInstanceOf(NullBroadcaster::class, $broadcaster);
        $this->assertSame($broadcasterInstance, $broadcaster);
        $this->assertContains('test_null_object', $this->registry->loaded());
    }

    /**
     * Test loading multiple broadcasters
     *
     * @return void
     */
    public function testLoadMultipleBroadcasters(): void
    {
        $nullConfig = ['className' => 'Crustum/Broadcasting.Null', 'key' => 'null-key'];
        $logConfig = ['className' => 'Crustum/Broadcasting.Log', 'level' => 'info'];

        $nullBroadcaster = $this->registry->load('null_test', $nullConfig);
        $logBroadcaster = $this->registry->load('log_test', $logConfig);

        $this->assertInstanceOf(NullBroadcaster::class, $nullBroadcaster);
        $this->assertInstanceOf(LogBroadcaster::class, $logBroadcaster);
        $this->assertContains('null_test', $this->registry->loaded());
        $this->assertContains('log_test', $this->registry->loaded());
    }

    /**
     * Test that loaded broadcasters implement BroadcasterInterface
     *
     * @return void
     */
    public function testLoadedBroadcastersImplementInterface(): void
    {
        $config = ['className' => 'Crustum/Broadcasting.Null', 'key' => 'test-key'];
        $broadcaster = $this->registry->load('test_interface', $config);

        $this->assertInstanceOf(BroadcasterInterface::class, $broadcaster);
    }

    /**
     * Test getting a loaded broadcaster
     *
     * @return void
     */
    public function testGetLoadedBroadcaster(): void
    {
        $config = ['className' => 'Crustum/Broadcasting.Null', 'key' => 'test-key'];
        $this->registry->load('test_get', $config);

        $broadcaster = $this->registry->get('test_get');

        $this->assertInstanceOf(NullBroadcaster::class, $broadcaster);
    }

    /**
     * Test getting a non-loaded broadcaster throws exception
     *
     * @return void
     */
    public function testGetNonLoadedBroadcasterThrowsException(): void
    {
        $this->expectException(CakeException::class);
        $this->expectExceptionMessage('Unknown object `non_existent`.');

        $this->registry->get('non_existent');
    }

    /**
     * Test checking if broadcaster is loaded
     *
     * @return void
     */
    public function testLoaded(): void
    {
        $this->assertNotContains('test_loaded', $this->registry->loaded());

        $config = ['className' => 'Crustum/Broadcasting.Null', 'key' => 'test-key'];
        $this->registry->load('test_loaded', $config);

        $this->assertContains('test_loaded', $this->registry->loaded());
    }

    /**
     * Test unloading a broadcaster
     *
     * @return void
     */
    public function testUnload(): void
    {
        $config = ['className' => 'Crustum/Broadcasting.Null', 'key' => 'test-key'];
        $this->registry->load('test_unload', $config);

        $this->assertContains('test_unload', $this->registry->loaded());

        $result = $this->registry->unload('test_unload');

        $this->assertSame($this->registry, $result);
        $this->assertNotContains('test_unload', $this->registry->loaded());
    }

    /**
     * Test resetting the registry
     *
     * @return void
     */
    public function testReset(): void
    {
        $config = ['className' => 'Crustum/Broadcasting.Null', 'key' => 'test-key'];
        $this->registry->load('test_reset1', $config);
        $this->registry->load('test_reset2', $config);

        $this->assertContains('test_reset1', $this->registry->loaded());
        $this->assertContains('test_reset2', $this->registry->loaded());

        $this->registry->reset();

        $this->assertNotContains('test_reset1', $this->registry->loaded());
        $this->assertNotContains('test_reset2', $this->registry->loaded());
    }

    /**
     * Test loading with invalid class name throws exception
     *
     * @return void
     */
    public function testLoadWithInvalidClassNameThrowsException(): void
    {
        $config = [
            'className' => 'NonExistentBroadcaster',
            'key' => 'test-key',
        ];

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Broadcaster engine `NonExistentBroadcaster` is not available.');

        $this->registry->load('test_invalid', $config);
    }

    /**
     * Test loading broadcaster without any configuration
     *
     * @return void
     */
    public function testLoadWithoutConfiguration(): void
    {
        $config = [
            'className' => 'Crustum/Broadcasting.Null',
        ];

        $broadcaster = $this->registry->load('test_no_config', $config);
        $this->assertInstanceOf(NullBroadcaster::class, $broadcaster);
        $this->assertContains('test_no_config', $this->registry->loaded());
    }

    /**
     * Test registry maintains separate instances
     *
     * @return void
     */
    public function testRegistryMaintainsSeparateInstances(): void
    {
        $config1 = ['className' => 'Crustum/Broadcasting.Null', 'key' => 'key1'];
        $config2 = ['className' => 'Crustum/Broadcasting.Null', 'key' => 'key2'];

        $broadcaster1 = $this->registry->load('instance1', $config1);
        $broadcaster2 = $this->registry->load('instance2', $config2);

        $this->assertNotSame($broadcaster1, $broadcaster2);
        $config1 = $broadcaster1->getConfig();
        $config2 = $broadcaster2->getConfig();
        $this->assertNotEquals($config1['key'], $config2['key']);
    }

    /**
     * Test accessing registry via get method
     *
     * @return void
     */
    public function testGetMethod(): void
    {
        $config = ['className' => 'Crustum/Broadcasting.Null', 'key' => 'test-key'];
        $this->registry->load('test_get_method', $config);

        $broadcaster = $this->registry->get('test_get_method');
        $this->assertInstanceOf(NullBroadcaster::class, $broadcaster);

        $this->assertContains('test_get_method', $this->registry->loaded());
        $this->assertNotContains('non_existent', $this->registry->loaded());
    }
}
