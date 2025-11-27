<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\TestSuite;

use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Broadcasting;
use Crustum\Broadcasting\TestSuite\BroadcastingTrait;
use Crustum\Broadcasting\TestSuite\TestBroadcaster;

/**
 * BroadcastingTrait Test
 *
 * Tests all assertion methods provided by BroadcastingTrait
 *
 * @uses \Crustum\Broadcasting\TestSuite\BroadcastingTrait
 */
class BroadcastingTraitTest extends TestCase
{
    use BroadcastingTrait;

    /**
     * Test assertBroadcastSent
     *
     * @return void
     */
    public function testAssertBroadcastSent(): void
    {
        Broadcasting::to('orders')->event('OrderCreated')->send();

        $this->assertBroadcastSent('OrderCreated');
    }

    /**
     * Test assertBroadcastNotSent
     *
     * @return void
     */
    public function testAssertBroadcastNotSent(): void
    {
        Broadcasting::to('orders')->event('OrderCreated')->send();

        $this->assertBroadcastNotSent('OrderDeleted');
    }

    /**
     * Test assertBroadcastCount
     *
     * @return void
     */
    public function testAssertBroadcastCount(): void
    {
        Broadcasting::to('orders')->event('OrderCreated')->send();
        Broadcasting::to('users')->event('UserRegistered')->send();
        Broadcasting::to('posts')->event('PostPublished')->send();

        $this->assertBroadcastCount(3);
    }

    /**
     * Test assertNoBroadcastsSent
     *
     * @return void
     */
    public function testAssertNoBroadcastsSent(): void
    {
        $this->assertNoBroadcastsSent();
    }

    /**
     * Test assertBroadcastSentToChannel
     *
     * @return void
     */
    public function testAssertBroadcastSentToChannel(): void
    {
        Broadcasting::to('orders')->event('OrderCreated')->send();

        $this->assertBroadcastSentToChannel('orders', 'OrderCreated');
    }

    /**
     * Test assertBroadcastSentToChannels
     *
     * @return void
     */
    public function testAssertBroadcastSentToChannels(): void
    {
        Broadcasting::to(['orders', 'admin', 'notifications'])
            ->event('OrderCreated')
            ->send();

        $this->assertBroadcastSentToChannels(['orders', 'admin'], 'OrderCreated');
    }

    /**
     * Test assertBroadcastNotSentToChannel
     *
     * @return void
     */
    public function testAssertBroadcastNotSentToChannel(): void
    {
        Broadcasting::to('orders')->event('OrderCreated')->send();

        $this->assertBroadcastNotSentToChannel('users', 'OrderCreated');
    }

    /**
     * Test assertBroadcastPayloadContains
     *
     * @return void
     */
    public function testAssertBroadcastPayloadContains(): void
    {
        Broadcasting::to('orders')
            ->event('OrderCreated')
            ->data(['order_id' => 123, 'total' => 99.99])
            ->send();

        $this->assertBroadcastPayloadContains('OrderCreated', 'order_id', 123);
        $this->assertBroadcastPayloadContains('OrderCreated', 'total', 99.99);
    }

    /**
     * Test assertBroadcastPayloadEquals
     *
     * @return void
     */
    public function testAssertBroadcastPayloadEquals(): void
    {
        Broadcasting::to('orders')
            ->event('OrderCreated')
            ->data(['order_id' => 123, 'status' => 'paid'])
            ->send();

        $this->assertBroadcastPayloadEquals('OrderCreated', [
            'order_id' => 123,
            'status' => 'paid',
        ]);
    }

    /**
     * Test assertBroadcastSentViaConnection
     *
     * @return void
     */
    public function testAssertBroadcastSentViaConnection(): void
    {
        Broadcasting::setConfig('pusher', [
            'className' => TestBroadcaster::class,
            'connectionName' => 'pusher',
        ]);
        Broadcasting::getRegistry()->reset();

        Broadcasting::to('orders')
            ->event('OrderCreated')
            ->connection('pusher')
            ->send();

        $this->assertBroadcastSentViaConnection('pusher', 'OrderCreated');
    }

    /**
     * Test assertBroadcastExcludedSocket
     *
     * @return void
     */
    public function testAssertBroadcastExcludedSocket(): void
    {
        Broadcasting::to('orders')
            ->event('OrderCreated')
            ->setSocket('socket-123')
            ->send();

        $this->assertBroadcastExcludedSocket('socket-123', 'OrderCreated');
    }

    /**
     * Test assertBroadcastSentTimes
     *
     * @return void
     */
    public function testAssertBroadcastSentTimes(): void
    {
        Broadcasting::to('orders')->event('OrderCreated')->send();
        Broadcasting::to('users')->event('OrderCreated')->send();
        Broadcasting::to('admin')->event('OrderCreated')->send();

        $this->assertBroadcastSentTimes('OrderCreated', 3);
    }

    /**
     * Test assertBroadcastToChannelTimes
     *
     * @return void
     */
    public function testAssertBroadcastToChannelTimes(): void
    {
        Broadcasting::to('orders')->event('OrderCreated')->send();
        Broadcasting::to('orders')->event('OrderCreated')->send();
        Broadcasting::to('users')->event('OrderCreated')->send();

        $this->assertBroadcastToChannelTimes('orders', 'OrderCreated', 2);
    }

    /**
     * Test assertBroadcastSentAt
     *
     * @return void
     */
    public function testAssertBroadcastSentAt(): void
    {
        Broadcasting::to('orders')->event('OrderCreated')->send();
        Broadcasting::to('users')->event('UserRegistered')->send();
        Broadcasting::to('posts')->event('PostPublished')->send();

        $this->assertBroadcastSentAt(0, 'OrderCreated');
        $this->assertBroadcastSentAt(1, 'UserRegistered');
        $this->assertBroadcastSentAt(2, 'PostPublished');
    }

    /**
     * Test getBroadcasts
     *
     * @return void
     */
    public function testGetBroadcasts(): void
    {
        Broadcasting::to('orders')->event('OrderCreated')->send();
        Broadcasting::to('users')->event('UserRegistered')->send();

        $broadcasts = $this->getBroadcasts();

        $this->assertCount(2, $broadcasts);
        $this->assertEquals('OrderCreated', $broadcasts[0]['event']);
        $this->assertEquals('UserRegistered', $broadcasts[1]['event']);
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
        Broadcasting::to('posts')->event('PostPublished')->send();

        $broadcasts = $this->getBroadcastsByEvent('OrderCreated');

        $this->assertCount(2, $broadcasts);
    }

    /**
     * Test getBroadcastsToChannel
     *
     * @return void
     */
    public function testGetBroadcastsToChannel(): void
    {
        Broadcasting::to('orders')->event('OrderCreated')->send();
        Broadcasting::to('orders')->event('OrderUpdated')->send();
        Broadcasting::to('users')->event('UserRegistered')->send();

        $broadcasts = $this->getBroadcastsToChannel('orders');

        $this->assertCount(2, $broadcasts);
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

        $broadcasts = $this->getBroadcastsByConnection('default');

        $this->assertCount(1, $broadcasts);
    }

    /**
     * Test trait setup and cleanup lifecycle
     *
     * @return void
     */
    public function testTraitLifecycle(): void
    {
        $this->assertCount(0, $this->getBroadcasts());

        Broadcasting::to('orders')->event('OrderCreated')->send();

        $this->assertCount(1, $this->getBroadcasts());
    }

    /**
     * Test multiple broadcasts to same channel
     *
     * @return void
     */
    public function testMultipleBroadcastsToSameChannel(): void
    {
        Broadcasting::to('orders')->event('OrderCreated')->send();
        Broadcasting::to('orders')->event('OrderUpdated')->send();
        Broadcasting::to('orders')->event('OrderShipped')->send();

        $broadcasts = $this->getBroadcastsToChannel('orders');

        $this->assertCount(3, $broadcasts);
        $this->assertEquals('OrderCreated', $broadcasts[0]['event']);
        $this->assertEquals('OrderUpdated', $broadcasts[1]['event']);
        $this->assertEquals('OrderShipped', $broadcasts[2]['event']);
    }

    /**
     * Test broadcast with complex payload
     *
     * @return void
     */
    public function testBroadcastWithComplexPayload(): void
    {
        $payload = [
            'order' => [
                'id' => 123,
                'items' => [
                    ['name' => 'Product 1', 'qty' => 2],
                    ['name' => 'Product 2', 'qty' => 1],
                ],
                'total' => 299.99,
            ],
            'user' => [
                'id' => 456,
                'name' => 'John Doe',
            ],
        ];

        Broadcasting::to('orders')->event('OrderCreated')->data($payload)->send();

        $broadcasts = $this->getBroadcasts();

        $this->assertEquals($payload, $broadcasts[0]['payload']);
        $this->assertBroadcastPayloadContains('OrderCreated', 'order', $payload['order']);
        $this->assertBroadcastPayloadContains('OrderCreated', 'user', $payload['user']);
    }
}
