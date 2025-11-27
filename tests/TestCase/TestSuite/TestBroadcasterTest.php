<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\TestSuite;

use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Broadcasting;
use Crustum\Broadcasting\TestSuite\TestBroadcaster;

/**
 * TestBroadcaster Test
 *
 * Tests the TestBroadcaster functionality
 *
 * @uses \Crustum\Broadcasting\TestSuite\TestBroadcaster
 */
class TestBroadcasterTest extends TestCase
{
    /**
     * Test setup
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        foreach (Broadcasting::configured() as $config) {
            Broadcasting::drop($config);
        }

        Broadcasting::setConfig('default', [
            'className' => 'Crustum/Broadcasting.Null',
        ]);
        Broadcasting::setConfig('test', [
            'className' => 'Crustum/Broadcasting.Null',
        ]);

        TestBroadcaster::replaceAllBroadcasters();
        TestBroadcaster::clearBroadcasts();
    }

    /**
     * Test teardown
     *
     * @return void
     */
    public function tearDown(): void
    {
        TestBroadcaster::clearBroadcasts();
        Broadcasting::getRegistry()->reset();
        parent::tearDown();
    }

    /**
     * Test replaceAllBroadcasters configures the broadcaster
     *
     * @return void
     */
    public function testReplaceAllBroadcasters(): void
    {
        TestBroadcaster::replaceAllBroadcasters();

        $broadcaster = Broadcasting::get('default');

        $this->assertInstanceOf(TestBroadcaster::class, $broadcaster);
    }

    /**
     * Test broadcast captures broadcast
     *
     * @return void
     */
    public function testBroadcastCapturesBroadcast(): void
    {
        Broadcasting::to('orders')->event('OrderCreated')->data(['order_id' => 123])->send();

        $broadcasts = TestBroadcaster::getBroadcasts();

        $this->assertCount(1, $broadcasts);
        $this->assertEquals('OrderCreated', $broadcasts[0]['event']);
        $this->assertEquals(['orders'], $broadcasts[0]['channels']);
        $this->assertEquals(123, $broadcasts[0]['payload']['order_id']);
    }

    /**
     * Test multiple broadcasts are captured
     *
     * @return void
     */
    public function testMultipleBroadcastsAreCaptured(): void
    {
        Broadcasting::to('orders')->event('OrderCreated')->send();
        Broadcasting::to('users')->event('UserRegistered')->send();
        Broadcasting::to('posts')->event('PostPublished')->send();

        $broadcasts = TestBroadcaster::getBroadcasts();

        $this->assertCount(3, $broadcasts);
    }

    /**
     * Test clearBroadcasts
     *
     * @return void
     */
    public function testClearBroadcasts(): void
    {
        Broadcasting::to('orders')->event('OrderCreated')->send();

        $this->assertCount(1, TestBroadcaster::getBroadcasts());

        TestBroadcaster::clearBroadcasts();

        $this->assertCount(0, TestBroadcaster::getBroadcasts());
    }

    /**
     * Test getBroadcastsToChannel
     *
     * @return void
     */
    public function testGetBroadcastsToChannel(): void
    {
        Broadcasting::to('orders')->event('OrderCreated')->send();
        Broadcasting::to('users')->event('UserRegistered')->send();
        Broadcasting::to('orders')->event('OrderUpdated')->send();

        $orderBroadcasts = TestBroadcaster::getBroadcastsToChannel('orders');
        $userBroadcasts = TestBroadcaster::getBroadcastsToChannel('users');

        $this->assertCount(2, $orderBroadcasts);
        $this->assertCount(1, $userBroadcasts);
    }

    /**
     * Test getBroadcastsByEvent
     *
     * @return void
     */
    public function testGetBroadcastsByEvent(): void
    {
        Broadcasting::to('orders')->event('OrderCreated')->send();
        Broadcasting::to('users')->event('OrderCreated')->send();
        Broadcasting::to('orders')->event('OrderUpdated')->send();

        $createdBroadcasts = TestBroadcaster::getBroadcastsByEvent('OrderCreated');
        $updatedBroadcasts = TestBroadcaster::getBroadcastsByEvent('OrderUpdated');

        $this->assertCount(2, $createdBroadcasts);
        $this->assertCount(1, $updatedBroadcasts);
    }

    /**
     * Test getBroadcastsByConnection
     *
     * @return void
     */
    public function testGetBroadcastsByConnection(): void
    {
        Broadcasting::setConfig('pusher', [
            'className' => TestBroadcaster::class,
            'connectionName' => 'pusher',
        ]);
        Broadcasting::getRegistry()->reset();

        Broadcasting::to('orders')->event('OrderCreated')->connection('default')->send();
        Broadcasting::to('users')->event('UserRegistered')->connection('pusher')->send();

        $defaultBroadcasts = TestBroadcaster::getBroadcastsByConnection('default');
        $pusherBroadcasts = TestBroadcaster::getBroadcastsByConnection('pusher');

        $this->assertCount(1, $defaultBroadcasts);
        $this->assertCount(1, $pusherBroadcasts);
        $this->assertEquals('default', $defaultBroadcasts[0]['connection']);
        $this->assertEquals('pusher', $pusherBroadcasts[0]['connection']);
    }

    /**
     * Test getBroadcastsExcludingSocket
     *
     * @return void
     */
    public function testGetBroadcastsExcludingSocket(): void
    {
        Broadcasting::to('orders')
            ->event('OrderCreated')
            ->setSocket('socket-123')
            ->send();

        $broadcasts = TestBroadcaster::getBroadcastsExcludingSocket('socket-123');

        $this->assertCount(1, $broadcasts);
        $this->assertEquals('socket-123', $broadcasts[0]['socket']);
    }

    /**
     * Test getBroadcastsToChannelWithEvent
     *
     * @return void
     */
    public function testGetBroadcastsToChannelWithEvent(): void
    {
        Broadcasting::to('orders')->event('OrderCreated')->send();
        Broadcasting::to('orders')->event('OrderUpdated')->send();
        Broadcasting::to('users')->event('OrderCreated')->send();

        $broadcasts = TestBroadcaster::getBroadcastsToChannelWithEvent('orders', 'OrderCreated');

        $this->assertCount(1, $broadcasts);
        $this->assertEquals('orders', $broadcasts[0]['channels'][0]);
        $this->assertEquals('OrderCreated', $broadcasts[0]['event']);
    }

    /**
     * Test broadcast to multiple channels
     *
     * @return void
     */
    public function testBroadcastToMultipleChannels(): void
    {
        Broadcasting::to(['orders', 'admin', 'notifications'])
            ->event('OrderCreated')
            ->send();

        $broadcasts = TestBroadcaster::getBroadcasts();

        $this->assertCount(1, $broadcasts);
        $this->assertEquals(['orders', 'admin', 'notifications'], $broadcasts[0]['channels']);
    }

    /**
     * Test broadcast with socket exclusion
     *
     * @return void
     */
    public function testBroadcastWithSocketExclusion(): void
    {
        Broadcasting::to('orders')
            ->event('OrderCreated')
            ->toOthers()
            ->setSocket('socket-abc-123')
            ->send();

        $broadcasts = TestBroadcaster::getBroadcasts();

        $this->assertCount(1, $broadcasts);
        $this->assertEquals('socket-abc-123', $broadcasts[0]['socket']);
    }
}
