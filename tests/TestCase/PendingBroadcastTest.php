<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase;

use Cake\Http\ServerRequest;
use Cake\Queue\QueueManager;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Broadcasting;
use Crustum\Broadcasting\PendingBroadcast;
use Crustum\Broadcasting\TestSuite\BroadcastingTrait;
use Crustum\Broadcasting\TestSuite\TestBroadcaster;
use Crustum\Broadcasting\TestSuite\TestQueueAdapter;
use RuntimeException;

/**
 * Pending Broadcast Test
 *
 * @package Crustum\Broadcasting\Test\TestCase
 */
class PendingBroadcastTest extends TestCase
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

        Broadcasting::setConfig('pusher', [
            'className' => TestBroadcaster::class,
        ]);

        QueueManager::setConfig('default', [
            'url' => 'null://',
        ]);
        QueueManager::setConfig('broadcasting', [
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
        QueueManager::drop('broadcasting');
        Router::setRequest(new ServerRequest());
        parent::tearDown();
    }

    /**
     * Test Broadcasting::to() creates PendingBroadcast
     *
     * @return void
     */
    public function testBroadcastingToCreatesPendingBroadcast(): void
    {
        $pending = Broadcasting::to('posts');

        $this->assertInstanceOf(PendingBroadcast::class, $pending);
    }

    /**
     * Test Broadcasting::private() creates PendingBroadcast with PrivateChannel
     *
     * @return void
     */
    public function testBroadcastingPrivateCreatesPendingBroadcast(): void
    {
        $pending = Broadcasting::private('chat.1');

        $this->assertInstanceOf(PendingBroadcast::class, $pending);
    }

    /**
     * Test Broadcasting::presence() creates PendingBroadcast with PresenceChannel
     *
     * @return void
     */
    public function testBroadcastingPresenceCreatesPendingBroadcast(): void
    {
        $pending = Broadcasting::presence('room.1');

        $this->assertInstanceOf(PendingBroadcast::class, $pending);
    }

    /**
     * Test fluent event() method
     *
     * @return void
     */
    public function testFluentEventMethod(): void
    {
        $pending = Broadcasting::to('posts')->event('PostCreated');

        $this->assertInstanceOf(PendingBroadcast::class, $pending);
    }

    /**
     * Test fluent data() method
     *
     * @return void
     */
    public function testFluentDataMethod(): void
    {
        $pending = Broadcasting::to('posts')->data(['id' => 1]);

        $this->assertInstanceOf(PendingBroadcast::class, $pending);
    }

    /**
     * Test fluent connection() method
     *
     * @return void
     */
    public function testFluentConnectionMethod(): void
    {
        $pending = Broadcasting::to('posts')->connection('pusher');

        $this->assertInstanceOf(PendingBroadcast::class, $pending);
    }

    /**
     * Test fluent toOthers() method
     *
     * @return void
     */
    public function testFluentToOthersMethod(): void
    {
        $request = new ServerRequest([
            'environment' => [
                'HTTP_X_SOCKET_ID' => 'test-socket-123',
            ],
        ]);
        Router::setRequest($request);

        $pending = Broadcasting::to('posts')->toOthers();

        $this->assertInstanceOf(PendingBroadcast::class, $pending);
        $this->assertEquals('test-socket-123', $pending->getSocket());
    }

    /**
     * Test send() broadcasts immediately
     *
     * @return void
     */
    public function testSendBroadcastsImmediately(): void
    {
        Broadcasting::to('posts')
            ->event('PostCreated')
            ->data(['id' => 1, 'title' => 'Test'])
            ->send();

        $this->assertBroadcastSent('PostCreated');
        $this->assertBroadcastSentToChannel('posts', 'PostCreated');
        $broadcasts = TestBroadcaster::getBroadcasts();
        $this->assertCount(1, $broadcasts);
        $this->assertEquals(['id' => 1, 'title' => 'Test'], $broadcasts[0]['payload']);
    }

    /**
     * Test queue() queues broadcast
     *
     * @return void
     */
    public function testQueueBroadcast(): void
    {
        Broadcasting::to('posts')
            ->event('PostCreated')
            ->data(['id' => 1, 'title' => 'Test'])
            ->queue('broadcasting');

        $this->assertBroadcastQueued('PostCreated');
        $this->assertBroadcastQueuedToChannel('posts', 'PostCreated');
        $this->assertNoBroadcastsSent();
        $queued = TestQueueAdapter::getQueuedBroadcastsByEvent('PostCreated');
        $this->assertCount(1, $queued);
        $this->assertEquals(['id' => 1, 'title' => 'Test'], $queued[0]['data']['payload']);
    }

    /**
     * Test complete fluent chain
     *
     * @return void
     */
    public function testCompleteFluentChain(): void
    {
        Broadcasting::to(['posts', 'notifications'])
            ->event('PostCreated')
            ->data(['id' => 1, 'title' => 'Test Post'])
            ->connection('pusher')
            ->send();

        $this->assertBroadcastSent('PostCreated');
        $broadcasts = TestBroadcaster::getBroadcasts();
        $this->assertCount(1, $broadcasts);
        $this->assertEquals(['posts', 'notifications'], $broadcasts[0]['channels']);
        $this->assertEquals(['id' => 1, 'title' => 'Test Post'], $broadcasts[0]['payload']);
    }

    /**
     * Test fluent chain with socket exclusion
     *
     * @return void
     */
    public function testFluentChainWithSocketExclusion(): void
    {
        $request = new ServerRequest([
            'environment' => [
                'HTTP_X_SOCKET_ID' => 'user-socket',
            ],
        ]);
        Router::setRequest($request);

        Broadcasting::to('posts')
            ->event('PostUpdated')
            ->data(['id' => 1])
            ->toOthers()
            ->send();

        $this->assertBroadcastSent('PostUpdated');
        $broadcasts = TestBroadcaster::getBroadcasts();
        $this->assertCount(1, $broadcasts);
        $this->assertEquals('user-socket', $broadcasts[0]['socket']);
    }

    /**
     * Test send() requires event name
     *
     * @return void
     */
    public function testSendRequiresEventName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Event name is required');

        Broadcasting::to('posts')
            ->data(['id' => 1])
            ->send();
    }

    /**
     * Test queue() requires event name
     *
     * @return void
     */
    public function testQueueRequiresEventName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Event name is required');

        Broadcasting::to('posts')
            ->data(['id' => 1])
            ->queue();
    }

    /**
     * Test auto-send in destructor
     *
     * @return void
     */
    public function testAutoSendInDestructor(): void
    {
        $pending = Broadcasting::to('posts')
            ->event('PostCreated')
            ->data(['id' => 1]);

        unset($pending);

        $this->assertBroadcastSent('PostCreated');
        $broadcasts = TestBroadcaster::getBroadcasts();
        $this->assertCount(1, $broadcasts);
    }

    /**
     * Test multiple channels
     *
     * @return void
     */
    public function testMultipleChannels(): void
    {
        Broadcasting::to(['posts', 'feed', 'notifications'])
            ->event('PostPublished')
            ->data(['id' => 1])
            ->send();

        $this->assertBroadcastSent('PostPublished');
        $broadcasts = TestBroadcaster::getBroadcasts();
        $this->assertCount(1, $broadcasts);
        $this->assertEquals(['posts', 'feed', 'notifications'], $broadcasts[0]['channels']);
    }
}
