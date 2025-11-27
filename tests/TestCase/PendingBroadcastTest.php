<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase;

use Cake\Http\ServerRequest;
use Cake\Queue\QueueManager;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Broadcasting;
use Crustum\Broadcasting\PendingBroadcast;
use RuntimeException;

/**
 * Pending Broadcast Test
 *
 * @package Crustum\Broadcasting\Test\TestCase
 */
class PendingBroadcastTest extends TestCase
{
    /**
     * Clear all Broadcasting configurations
     *
     * @return void
     */
    protected function clearBroadcastingConfigurations(): void
    {
        foreach (Broadcasting::configured() as $configName) {
            Broadcasting::drop($configName);
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

        Broadcasting::setConfig('default', [
            'className' => 'Crustum/Broadcasting.Null',
        ]);
        Broadcasting::setConfig('pusher', [
            'className' => 'Crustum/Broadcasting.Null',
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

        $this->assertTrue(true);
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

        $this->assertTrue(true);
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

        $this->assertTrue(true);
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

        $this->assertTrue(true);
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

        $this->assertTrue(true);
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

        $this->assertTrue(true);
    }
}
