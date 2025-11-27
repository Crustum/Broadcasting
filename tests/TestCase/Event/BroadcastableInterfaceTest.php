<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Event;

use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Channel\Channel;
use Crustum\Broadcasting\Test\TestApp\Event\TestBroadcastableClass;

/**
 * Broadcastable Interface Test
 *
 * @package Crustum\Broadcasting\Test\TestCase\Event
 */
class BroadcastableInterfaceTest extends TestCase
{
    /**
     * Test class implementing the interface.
     */
    protected TestBroadcastableClass $testClass;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->testClass = new TestBroadcastableClass();
    }

    /**
     * Test broadcastChannel method returns expected channels.
     *
     * @return void
     */
    public function testBroadcastOn(): void
    {
        $channels = $this->testClass->broadcastChannel();

        $this->assertIsArray($channels);
        $this->assertCount(2, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertInstanceOf(Channel::class, $channels[1]);
        $this->assertEquals('test-channel-1', $channels[0]->getName());
        $this->assertEquals('test-channel-2', $channels[1]->getName());
    }

    /**
     * Test broadcastEvent method returns expected event name.
     *
     * @return void
     */
    public function testBroadcastAs(): void
    {
        $eventName = $this->testClass->broadcastEvent();

        $this->assertEquals('test.event', $eventName);
    }

    /**
     * Test broadcastData method returns expected payload.
     *
     * @return void
     */
    public function testBroadcastWith(): void
    {
        $payload = $this->testClass->broadcastData();

        $this->assertIsArray($payload);
        $this->assertEquals('test-value', $payload['test-key']);
        $this->assertEquals(123, $payload['test-number']);
    }

    /**
     * Test broadcastWhen method returns true.
     *
     * @return void
     */
    public function testBroadcastWhen(): void
    {
        $shouldBroadcast = $this->testClass->broadcastWhen();

        $this->assertTrue($shouldBroadcast);
    }

    /**
     * Test broadcastSocket method returns null by default.
     *
     * @return void
     */
    public function testBroadcastSocket(): void
    {
        $socket = $this->testClass->broadcastSocket();

        $this->assertNull($socket);
    }

    /**
     * Test setChannels method updates channels.
     *
     * @return void
     */
    public function testSetChannels(): void
    {
        $newChannel = new Channel('new-channel');
        $this->testClass->setChannels($newChannel);

        $channels = $this->testClass->broadcastChannel();
        $this->assertCount(1, $channels);
        $this->assertEquals('new-channel', $channels[0]->getName());
    }

    /**
     * Test setEventName method updates event name.
     *
     * @return void
     */
    public function testSetEventName(): void
    {
        $this->testClass->setEventName('new.event');

        $this->assertEquals('new.event', $this->testClass->broadcastEvent());
    }

    /**
     * Test setData method updates data.
     *
     * @return void
     */
    public function testSetData(): void
    {
        $newData = ['new-key' => 'new-value'];
        $this->testClass->setData($newData);

        $this->assertEquals($newData, $this->testClass->broadcastData());
    }

    /**
     * Test setShouldBroadcast method updates broadcast condition.
     *
     * @return void
     */
    public function testSetShouldBroadcast(): void
    {
        $this->testClass->setShouldBroadcast(false);

        $this->assertFalse($this->testClass->broadcastWhen());
    }

    /**
     * Test setSocket method updates socket.
     *
     * @return void
     */
    public function testSetSocket(): void
    {
        $this->testClass->setSocket('socket-123');

        $this->assertEquals('socket-123', $this->testClass->broadcastSocket());
    }

    /**
     * Test broadcastQueue method returns expected queue.
     *
     * @return void
     */
    public function testBroadcastQueue(): void
    {
        $queue = $this->testClass->broadcastQueue();

        $this->assertEquals('high', $queue);
    }

    /**
     * Test broadcastDelay method returns null by default.
     *
     * @return void
     */
    public function testBroadcastDelay(): void
    {
        $delay = $this->testClass->broadcastDelay();

        $this->assertNull($delay);
    }

    /**
     * Test broadcastExpires method returns null by default.
     *
     * @return void
     */
    public function testBroadcastExpires(): void
    {
        $expires = $this->testClass->broadcastExpires();

        $this->assertNull($expires);
    }

    /**
     * Test broadcastPriority method returns null by default.
     *
     * @return void
     */
    public function testBroadcastPriority(): void
    {
        $priority = $this->testClass->broadcastPriority();

        $this->assertNull($priority);
    }

    /**
     * Test setQueue method updates queue.
     *
     * @return void
     */
    public function testSetQueue(): void
    {
        $this->testClass->setQueue('low');

        $this->assertEquals('low', $this->testClass->broadcastQueue());
    }

    /**
     * Test setDelay method updates delay.
     *
     * @return void
     */
    public function testSetDelay(): void
    {
        $this->testClass->setDelay(60);

        $this->assertEquals(60, $this->testClass->broadcastDelay());
    }

    /**
     * Test setExpires method updates expires.
     *
     * @return void
     */
    public function testSetExpires(): void
    {
        $this->testClass->setExpires(3600);

        $this->assertEquals(3600, $this->testClass->broadcastExpires());
    }

    /**
     * Test setPriority method updates priority.
     *
     * @return void
     */
    public function testSetPriority(): void
    {
        $this->testClass->setPriority('high');

        $this->assertEquals('high', $this->testClass->broadcastPriority());
    }
}
