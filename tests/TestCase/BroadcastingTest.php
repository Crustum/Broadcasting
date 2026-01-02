<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase;

use Cake\Core\Configure;
use Cake\Queue\QueueManager;
use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Broadcaster\BroadcasterInterface;
use Crustum\Broadcasting\Broadcaster\NullBroadcaster;
use Crustum\Broadcasting\Broadcasting;
use Crustum\Broadcasting\Channel\Channel;
use Crustum\Broadcasting\Event\BroadcastableInterface;
use Crustum\Broadcasting\Exception\InvalidBroadcasterException;
use Crustum\Broadcasting\PendingBroadcast;
use Crustum\Broadcasting\Queue\QueueAdapterInterface;
use Crustum\Broadcasting\Registry\BroadcasterRegistry;
use Crustum\Broadcasting\Test\TestApp\Event\TestBroadcastableClass;
use Crustum\Broadcasting\TestSuite\BroadcastingTrait;
use Crustum\Broadcasting\TestSuite\TestBroadcaster;
use Crustum\Broadcasting\TestSuite\TestQueueAdapter;
use Exception;
use LogicException;

/**
 * Broadcasting Test
 *
 * Comprehensive tests for the Broadcasting facade class.
 */
class BroadcastingTest extends TestCase
{
    use BroadcastingTrait;

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
        Broadcasting::enable();
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
        TestQueueAdapter::replaceQueueAdapter();
        TestQueueAdapter::clearQueuedJobs();

        Broadcasting::setConfig('test', [
            'className' => TestBroadcaster::class,
        ]);

        Broadcasting::setConfig('pusher', [
            'className' => TestBroadcaster::class,
        ]);

        QueueManager::setConfig('default', [
            'url' => 'null://',
        ]);
    }

    /**
     * Tear down test case
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->clearBroadcastingConfigurations();
        QueueManager::drop('default');
        parent::tearDown();
    }

    /**
     * Test getRegistry returns BroadcasterRegistry instance
     *
     * @return void
     */
    public function testGetRegistry(): void
    {
        $registry = Broadcasting::getRegistry();

        $this->assertInstanceOf(BroadcasterRegistry::class, $registry);
    }

    /**
     * Test setRegistry sets custom registry
     *
     * @return void
     */
    public function testSetRegistry(): void
    {
        $customRegistry = new BroadcasterRegistry();
        Broadcasting::setRegistry($customRegistry);

        $this->assertSame($customRegistry, Broadcasting::getRegistry());
    }

    /**
     * Test setQueueAdapter sets custom adapter
     *
     * @return void
     */
    public function testSetQueueAdapter(): void
    {
        $adapter = $this->createStub(QueueAdapterInterface::class);
        Broadcasting::setQueueAdapter($adapter);

        $this->assertSame($adapter, Broadcasting::getQueueAdapter());
    }

    /**
     * Test getQueueAdapter returns default adapter
     *
     * @return void
     */
    public function testGetQueueAdapter(): void
    {
        $adapter = Broadcasting::getQueueAdapter();

        $this->assertInstanceOf(QueueAdapterInterface::class, $adapter);
    }

    /**
     * Test enable enables broadcasting
     *
     * @return void
     */
    public function testEnable(): void
    {
        Broadcasting::disable();
        $this->assertFalse(Broadcasting::enabled());

        Broadcasting::enable();
        $this->assertTrue(Broadcasting::enabled());
    }

    /**
     * Test disable disables broadcasting
     *
     * @return void
     */
    public function testDisable(): void
    {
        Broadcasting::enable();
        $this->assertTrue(Broadcasting::enabled());

        Broadcasting::disable();
        $this->assertFalse(Broadcasting::enabled());
    }

    /**
     * Test enabled returns current state
     *
     * @return void
     */
    public function testEnabled(): void
    {
        Broadcasting::enable();
        $this->assertTrue(Broadcasting::enabled());

        Broadcasting::disable();
        $this->assertFalse(Broadcasting::enabled());
    }

    /**
     * Test get returns NullBroadcaster when disabled
     *
     * @return void
     */
    public function testGetReturnsNullBroadcasterWhenDisabled(): void
    {
        Broadcasting::disable();

        $broadcaster = Broadcasting::get('test');

        $this->assertInstanceOf(NullBroadcaster::class, $broadcaster);
    }

    /**
     * Test get returns configured broadcaster when enabled
     *
     * @return void
     */
    public function testGetReturnsConfiguredBroadcasterWhenEnabled(): void
    {
        Broadcasting::enable();
        Broadcasting::getRegistry()->reset();

        $broadcaster = Broadcasting::get('test');

        $this->assertInstanceOf(BroadcasterInterface::class, $broadcaster);
        $this->assertInstanceOf(TestBroadcaster::class, $broadcaster);
    }

    /**
     * Test get builds broadcaster if not in registry
     *
     * @return void
     */
    public function testGetBuildsBroadcasterIfNotInRegistry(): void
    {
        Broadcasting::enable();
        Broadcasting::getRegistry()->reset();

        $broadcaster = Broadcasting::get('test');

        $this->assertInstanceOf(BroadcasterInterface::class, $broadcaster);
    }

    /**
     * Test get throws exception for invalid broadcaster
     *
     * @return void
     */
    public function testGetThrowsExceptionForInvalidBroadcaster(): void
    {
        $this->expectException(InvalidBroadcasterException::class);

        Broadcasting::get('nonexistent');
    }

    /**
     * Test _buildBroadcaster with valid configuration
     *
     * @return void
     */
    public function testBuildBroadcasterWithValidConfiguration(): void
    {
        Broadcasting::setConfig('valid', [
            'className' => TestBroadcaster::class,
        ]);

        $broadcaster = Broadcasting::get('valid');

        $this->assertInstanceOf(BroadcasterInterface::class, $broadcaster);
    }

    /**
     * Test _buildBroadcaster with missing className
     *
     * @return void
     */
    public function testBuildBroadcasterWithMissingClassName(): void
    {
        Broadcasting::setConfig('invalid', []);

        $this->expectException(InvalidBroadcasterException::class);
        $this->expectExceptionMessage('The `invalid` broadcasting configuration does not exist.');

        Broadcasting::get('invalid');
    }

    /**
     * Test _buildBroadcaster with fallback configuration
     *
     * @return void
     */
    public function testBuildBroadcasterWithFallback(): void
    {
        Broadcasting::setConfig('fallback_test', [
            'className' => TestBroadcaster::class,
        ]);

        Broadcasting::setConfig('with_fallback', [
            'className' => 'NonExistentClass',
            'fallback' => 'fallback_test',
        ]);

        $broadcaster = Broadcasting::get('with_fallback');

        $this->assertInstanceOf(BroadcasterInterface::class, $broadcaster);
    }

    /**
     * Test _buildBroadcaster with fallback to self throws exception
     *
     * @return void
     */
    public function testBuildBroadcasterWithFallbackToSelfThrowsException(): void
    {
        Broadcasting::setConfig('self_fallback', [
            'className' => 'NonExistentClass',
            'fallback' => 'self_fallback',
        ]);

        $this->expectException(InvalidBroadcasterException::class);
        $this->expectExceptionMessage('`self_fallback` broadcasting configuration cannot fallback to itself.');

        Broadcasting::get('self_fallback');
    }

    /**
     * Test _buildBroadcaster with fallback false throws exception
     *
     * @return void
     */
    public function testBuildBroadcasterWithFallbackFalseThrowsException(): void
    {
        Broadcasting::setConfig('no_fallback', [
            'className' => 'NonExistentClass',
            'fallback' => false,
        ]);

        $this->expectException(Exception::class);

        Broadcasting::get('no_fallback');
    }

    /**
     * Test to creates PendingBroadcast with string channel
     *
     * @return void
     */
    public function testToWithStringChannel(): void
    {
        $pending = Broadcasting::to('posts');

        $this->assertInstanceOf(PendingBroadcast::class, $pending);
    }

    /**
     * Test to creates PendingBroadcast with array channels
     *
     * @return void
     */
    public function testToWithArrayChannels(): void
    {
        $pending = Broadcasting::to(['posts', 'notifications']);

        $this->assertInstanceOf(PendingBroadcast::class, $pending);
    }

    /**
     * Test to creates PendingBroadcast with Channel object
     *
     * @return void
     */
    public function testToWithChannelObject(): void
    {
        $channel = new Channel('posts');
        $pending = Broadcasting::to($channel);

        $this->assertInstanceOf(PendingBroadcast::class, $pending);
    }

    /**
     * Test private creates PendingBroadcast with PrivateChannel
     *
     * @return void
     */
    public function testPrivate(): void
    {
        $pending = Broadcasting::private('chat.1');

        $this->assertInstanceOf(PendingBroadcast::class, $pending);
    }

    /**
     * Test presence creates PendingBroadcast with PresenceChannel
     *
     * @return void
     */
    public function testPresence(): void
    {
        $pending = Broadcasting::presence('room.1');

        $this->assertInstanceOf(PendingBroadcast::class, $pending);
    }

    /**
     * Test event with BroadcastableInterface
     *
     * @return void
     */
    public function testEventWithBroadcastableInterface(): void
    {
        $event = $this->createStub(BroadcastableInterface::class);
        $event->method('broadcastChannel')->willReturn(new Channel('posts'));
        $event->method('broadcastEvent')->willReturn('PostCreated');
        $event->method('broadcastData')->willReturn(['id' => 1]);
        $event->method('broadcastSocket')->willReturn(null);

        $pending = Broadcasting::event($event);

        $this->assertInstanceOf(PendingBroadcast::class, $pending);
    }

    /**
     * Test event with ConditionalInterface that should broadcast
     *
     * @return void
     */
    public function testEventWithConditionalInterfaceShouldBroadcast(): void
    {
        $event = new TestBroadcastableClass();
        $event->setEventName('PostCreated')
            ->setChannels(new Channel('posts'))
            ->setData(['id' => 1])
            ->setShouldBroadcast(true);

        $pending = Broadcasting::event($event);

        $this->assertInstanceOf(PendingBroadcast::class, $pending);
    }

    /**
     * Test event with ConditionalInterface that should not broadcast
     *
     * @return void
     */
    public function testEventWithConditionalInterfaceShouldNotBroadcast(): void
    {
        $event = new TestBroadcastableClass();
        $event->setEventName('PostCreated')
            ->setChannels(new Channel('posts'))
            ->setData(['id' => 1])
            ->setShouldBroadcast(false);

        $pending = Broadcasting::event($event);

        $this->assertInstanceOf(PendingBroadcast::class, $pending);
    }

    /**
     * Test event with QueueableInterface
     *
     * @return void
     */
    public function testEventWithQueueableInterface(): void
    {
        $event = new TestBroadcastableClass();
        $event->setEventName('PostCreated')
            ->setChannels(new Channel('posts'))
            ->setData(['id' => 1])
            ->setQueue('broadcasting')
            ->setDelay(60)
            ->setExpires(3600)
            ->setPriority('high');

        $pending = Broadcasting::event($event);

        $this->assertInstanceOf(PendingBroadcast::class, $pending);
    }

    /**
     * Test event with all interfaces combined
     *
     * @return void
     */
    public function testEventWithAllInterfaces(): void
    {
        $event = new TestBroadcastableClass();
        $event->setEventName('PostCreated')
            ->setChannels(new Channel('posts'))
            ->setData(['id' => 1])
            ->setSocket('socket-123')
            ->setShouldBroadcast(true)
            ->setQueue('broadcasting')
            ->setDelay(60)
            ->setExpires(3600)
            ->setPriority('high');

        $pending = Broadcasting::event($event);

        $this->assertInstanceOf(PendingBroadcast::class, $pending);
    }

    /**
     * Test channel registers channel callback
     *
     * @return void
     */
    public function testChannel(): void
    {
        Broadcasting::channel('private-user.{id}', function ($user, $id) {
            return $user->id === $id;
        }, [], 'test');

        $broadcaster = Broadcasting::get('test');
        $this->assertInstanceOf(BroadcasterInterface::class, $broadcaster);
    }

    /**
     * Test channel with invalid connection returns silently
     *
     * @return void
     */
    public function testChannelWithInvalidConnectionReturnsSilently(): void
    {
        Broadcasting::channel('private-user.{id}', function () {
            return true;
        }, [], 'nonexistent');

        $this->expectNotToPerformAssertions();
    }

    /**
     * Test routes loads channels file
     *
     * @return void
     */
    public function testRoutes(): void
    {
        $channelsFile = CONFIG . 'channels.php';
        if (!file_exists($channelsFile)) {
            $this->markTestSkipped('channels.php file does not exist');
        }

        Broadcasting::routes();

        $this->expectNotToPerformAssertions();
    }

    /**
     * Test routes with custom channels file
     *
     * @return void
     */
    public function testRoutesWithCustomChannelsFile(): void
    {
        Configure::write('Broadcasting.channels_file', CONFIG . 'channels.php');

        Broadcasting::routes();

        $this->expectNotToPerformAssertions();
    }

    /**
     * Test broadcast with single channel
     *
     * @return void
     */
    public function testBroadcastWithSingleChannel(): void
    {
        Broadcasting::broadcast('posts', 'PostCreated', ['id' => 1]);

        $this->assertBroadcastSent('PostCreated');
        $this->assertBroadcastSentToChannel('posts', 'PostCreated');
    }

    /**
     * Test broadcast with multiple channels
     *
     * @return void
     */
    public function testBroadcastWithMultipleChannels(): void
    {
        Broadcasting::broadcast(['posts', 'notifications'], 'PostCreated', ['id' => 1]);

        $this->assertBroadcastSent('PostCreated');
        $broadcasts = TestBroadcaster::getBroadcasts();
        $this->assertCount(1, $broadcasts);
        $this->assertEquals(['posts', 'notifications'], $broadcasts[0]['channels']);
    }

    /**
     * Test broadcast with custom config
     *
     * @return void
     */
    public function testBroadcastWithCustomConfig(): void
    {
        Broadcasting::broadcast('posts', 'PostCreated', ['id' => 1], 'pusher');

        $this->assertBroadcastSent('PostCreated');
    }

    /**
     * Test broadcast with socket exclusion
     *
     * @return void
     */
    public function testBroadcastWithSocketExclusion(): void
    {
        Broadcasting::broadcast('posts', 'PostCreated', ['id' => 1], 'default', 'socket-123');

        $this->assertBroadcastSent('PostCreated');
        $broadcasts = TestBroadcaster::getBroadcasts();
        $this->assertCount(1, $broadcasts);
        $this->assertEquals('socket-123', $broadcasts[0]['socket']);
    }

    /**
     * Test queueBroadcast queues broadcast
     *
     * @return void
     */
    public function testQueueBroadcast(): void
    {
        Broadcasting::queueBroadcast('posts', 'PostCreated', ['id' => 1]);

        $this->assertBroadcastQueued('PostCreated');
        $this->assertNoBroadcastsSent();
    }

    /**
     * Test queueBroadcast with multiple channels
     *
     * @return void
     */
    public function testQueueBroadcastWithMultipleChannels(): void
    {
        Broadcasting::queueBroadcast(['posts', 'notifications'], 'PostCreated', ['id' => 1]);

        $this->assertBroadcastQueued('PostCreated');
    }

    /**
     * Test queueBroadcast with options
     *
     * @return void
     */
    public function testQueueBroadcastWithOptions(): void
    {
        Broadcasting::queueBroadcast('posts', 'PostCreated', ['id' => 1], 'default', [
            'delay' => 60,
            'priority' => 'high',
        ]);

        $this->assertBroadcastQueued('PostCreated');
    }

    /**
     * Test setConfig with array key
     *
     * @return void
     */
    public function testSetConfigWithArrayKey(): void
    {
        Broadcasting::setConfig([
            'config1' => ['className' => TestBroadcaster::class],
            'config2' => ['className' => TestBroadcaster::class],
        ]);

        $this->assertContains('config1', Broadcasting::configured());
        $this->assertContains('config2', Broadcasting::configured());
    }

    /**
     * Test setConfig with string key
     *
     * @return void
     */
    public function testSetConfigWithStringKey(): void
    {
        Broadcasting::setConfig('custom', ['className' => TestBroadcaster::class]);

        $this->assertContains('custom', Broadcasting::configured());
    }

    /**
     * Test setConfig with null config throws exception
     *
     * @return void
     */
    public function testSetConfigWithNullConfigThrowsException(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('If config is null, key must be an array.');

        Broadcasting::setConfig('invalid');
    }

    /**
     * Test setConfig with non-string key throws exception
     *
     * @return void
     */
    public function testSetConfigWithNonStringKeyThrowsException(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('If config is not null, key must be a string.');

        /** @phpstan-ignore-next-line */
        Broadcasting::setConfig(['invalid'], ['className' => TestBroadcaster::class]);
    }

    /**
     * Test setConfig drops existing config
     *
     * @return void
     */
    public function testSetConfigDropsExistingConfig(): void
    {
        Broadcasting::setConfig('existing', ['className' => TestBroadcaster::class]);
        Broadcasting::get('existing');

        Broadcasting::setConfig('existing', ['className' => TestBroadcaster::class]);

        $this->assertContains('existing', Broadcasting::configured());
    }

    /**
     * Test initFromConfigure initializes from array
     *
     * @return void
     */
    public function testInitFromConfigure(): void
    {
        $connections = [
            'init1' => ['className' => TestBroadcaster::class],
            'init2' => ['className' => TestBroadcaster::class],
        ];

        Broadcasting::initFromConfigure($connections);

        $this->assertContains('init1', Broadcasting::configured());
        $this->assertContains('init2', Broadcasting::configured());
    }

    /**
     * Test initFromConfigure does not override existing config
     *
     * @return void
     */
    public function testInitFromConfigureDoesNotOverrideExisting(): void
    {
        Broadcasting::setConfig('existing', ['className' => TestBroadcaster::class, 'custom' => 'value']);

        Broadcasting::initFromConfigure([
            'existing' => ['className' => TestBroadcaster::class, 'custom' => 'new_value'],
        ]);

        $this->assertContains('existing', Broadcasting::configured());
    }

    /**
     * Test configured returns list of configured broadcasters
     *
     * @return void
     */
    public function testConfigured(): void
    {
        Broadcasting::setConfig('test1', ['className' => TestBroadcaster::class]);
        Broadcasting::setConfig('test2', ['className' => TestBroadcaster::class]);

        $configured = Broadcasting::configured();

        $this->assertContains('test1', $configured);
        $this->assertContains('test2', $configured);
    }
}
