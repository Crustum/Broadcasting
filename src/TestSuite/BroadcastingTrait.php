<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\TestSuite;

use Crustum\Broadcasting\Broadcasting;
use Crustum\Broadcasting\TestSuite\Constraint\Broadcasting\BroadcastCount;
use Crustum\Broadcasting\TestSuite\Constraint\Broadcasting\BroadcastPayloadContains;
use Crustum\Broadcasting\TestSuite\Constraint\Broadcasting\BroadcastSent;
use Crustum\Broadcasting\TestSuite\Constraint\Broadcasting\BroadcastSentTimes;
use Crustum\Broadcasting\TestSuite\Constraint\Broadcasting\BroadcastSentToChannel;
use Crustum\Broadcasting\TestSuite\Constraint\Broadcasting\BroadcastSentViaConnection;
use Crustum\Broadcasting\TestSuite\Constraint\Broadcasting\NoBroadcastSent;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

/**
 * Broadcasting Trait
 *
 * Make assertions on broadcasts sent through TestBroadcaster.
 *
 * After adding the trait to your test case, all broadcasts will be captured
 * instead of being sent, allowing you to make assertions.
 *
 * Usage:
 * ```
 * class MyTest extends TestCase
 * {
 *     use BroadcastingTrait;
 *
 *     public function testBroadcastSent(): void
 *     {
 *         Broadcasting::to('orders')->event('OrderCreated')->send();
 *
 *         $this->assertBroadcastSent('OrderCreated');
 *         $this->assertBroadcastSentToChannel('orders', 'OrderCreated');
 *     }
 * }
 * ```
 */
trait BroadcastingTrait
{
    /**
     * Setup test broadcaster
     *
     * Replaces the broadcaster with TestBroadcaster
     * to capture broadcasts instead of sending them.
     *
     * @return void
     */
    #[Before]
    public function setupTestBroadcaster(): void
    {
        foreach (Broadcasting::configured() as $config) {
            Broadcasting::drop($config);
        }

        Broadcasting::setConfig('default', [
            'className' => TestBroadcaster::class,
            'connectionName' => 'default',
        ]);

        Broadcasting::getRegistry()->reset();
        TestBroadcaster::clearBroadcasts();
    }

    /**
     * Cleanup broadcasts
     *
     * Clears all captured broadcasts after each test.
     *
     * @return void
     */
    #[After]
    public function cleanupBroadcastingTrait(): void
    {
        TestBroadcaster::clearBroadcasts();
        Broadcasting::getRegistry()->reset();
    }

    /**
     * Assert a broadcast of a specific event was sent
     *
     * @param string $event Event name
     * @param string $message Optional assertion message
     * @return void
     */
    public function assertBroadcastSent(string $event, string $message = ''): void
    {
        $this->assertThat($event, new BroadcastSent(), $message);
    }

    /**
     * Assert a broadcast at a specific index was sent
     *
     * @param int $at Broadcast index (0-based)
     * @param string $event Event name
     * @param string $message Optional assertion message
     * @return void
     */
    public function assertBroadcastSentAt(int $at, string $event, string $message = ''): void
    {
        $this->assertThat($event, new BroadcastSent($at), $message);
    }

    /**
     * Assert a broadcast was not sent
     *
     * @param string $event Event name
     * @param string $message Optional assertion message
     * @return void
     */
    public function assertBroadcastNotSent(string $event, string $message = ''): void
    {
        $broadcasts = TestBroadcaster::getBroadcastsByEvent($event);
        $this->assertEmpty(
            $broadcasts,
            $message ?: "Broadcast {$event} was sent unexpectedly",
        );
    }

    /**
     * Assert no broadcasts were sent
     *
     * @param string $message Optional assertion message
     * @return void
     */
    public function assertNoBroadcastsSent(string $message = ''): void
    {
        $this->assertThat(null, new NoBroadcastSent(), $message);
    }

    /**
     * Assert a specific count of broadcasts were sent
     *
     * @param int $count Expected broadcast count
     * @param string $message Optional assertion message
     * @return void
     */
    public function assertBroadcastCount(int $count, string $message = ''): void
    {
        $this->assertThat($count, new BroadcastCount(), $message);
    }

    /**
     * Assert a broadcast was sent to a specific channel
     *
     * @param string $channel Channel name
     * @param string $event Event name
     * @param string $message Optional assertion message
     * @return void
     */
    public function assertBroadcastSentToChannel(
        string $channel,
        string $event,
        string $message = '',
    ): void {
        $this->assertThat(
            ['channel' => $channel, 'event' => $event],
            new BroadcastSentToChannel(),
            $message,
        );
    }

    /**
     * Assert a broadcast was sent to multiple channels
     *
     * @param array<string> $channels Channel names
     * @param string $event Event name
     * @param string $message Optional assertion message
     * @return void
     */
    public function assertBroadcastSentToChannels(
        array $channels,
        string $event,
        string $message = '',
    ): void {
        $broadcasts = TestBroadcaster::getBroadcastsByEvent($event);

        foreach ($channels as $channel) {
            $found = false;
            foreach ($broadcasts as $broadcast) {
                if (in_array($channel, $broadcast['channels'])) {
                    $found = true;
                    break;
                }
            }

            $this->assertTrue(
                $found,
                $message ?: "Broadcast {$event} was not sent to channel {$channel}",
            );
        }
    }

    /**
     * Assert a broadcast was not sent to a specific channel
     *
     * @param string $channel Channel name
     * @param string $event Event name
     * @param string $message Optional assertion message
     * @return void
     */
    public function assertBroadcastNotSentToChannel(
        string $channel,
        string $event,
        string $message = '',
    ): void {
        $broadcasts = TestBroadcaster::getBroadcastsToChannelWithEvent($channel, $event);
        $this->assertEmpty(
            $broadcasts,
            $message ?: "Broadcast {$event} was sent to channel {$channel} unexpectedly",
        );
    }

    /**
     * Assert a broadcast contains specific data in its payload
     *
     * @param string $event Event name
     * @param string $key Payload key
     * @param mixed $value Expected value
     * @param string $message Optional assertion message
     * @return void
     */
    public function assertBroadcastPayloadContains(
        string $event,
        string $key,
        mixed $value,
        string $message = '',
    ): void {
        $this->assertThat(
            ['event' => $event, 'key' => $key, 'value' => $value],
            new BroadcastPayloadContains(),
            $message,
        );
    }

    /**
     * Assert a broadcast payload equals specific data
     *
     * @param string $event Event name
     * @param array<string, mixed> $payload Expected payload
     * @param string $message Optional assertion message
     * @return void
     */
    public function assertBroadcastPayloadEquals(
        string $event,
        array $payload,
        string $message = '',
    ): void {
        $broadcasts = TestBroadcaster::getBroadcastsByEvent($event);
        $this->assertNotEmpty($broadcasts, "Broadcast {$event} was not sent");

        $actualPayload = $broadcasts[0]['payload'];
        unset($actualPayload['socket']);

        $this->assertEquals(
            $payload,
            $actualPayload,
            $message ?: "Broadcast {$event} payload does not match",
        );
    }

    /**
     * Assert a broadcast was sent via a specific connection
     *
     * @param string $connection Connection name
     * @param string $event Event name
     * @param string $message Optional assertion message
     * @return void
     */
    public function assertBroadcastSentViaConnection(
        string $connection,
        string $event,
        string $message = '',
    ): void {
        $this->assertThat(
            ['connection' => $connection, 'event' => $event],
            new BroadcastSentViaConnection(),
            $message,
        );
    }

    /**
     * Assert a broadcast excluded a specific socket
     *
     * @param string $socket Socket ID
     * @param string $event Event name
     * @param string $message Optional assertion message
     * @return void
     */
    public function assertBroadcastExcludedSocket(
        string $socket,
        string $event,
        string $message = '',
    ): void {
        $broadcasts = TestBroadcaster::getBroadcastsByEvent($event);
        $this->assertNotEmpty($broadcasts, "Broadcast {$event} was not sent");

        $found = false;
        foreach ($broadcasts as $broadcast) {
            if ($broadcast['socket'] === $socket) {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            $message ?: "Broadcast {$event} did not exclude socket {$socket}",
        );
    }

    /**
     * Assert a broadcast was sent a specific number of times
     *
     * @param string $event Event name
     * @param int $times Expected number of times
     * @param string $message Optional assertion message
     * @return void
     */
    public function assertBroadcastSentTimes(string $event, int $times, string $message = ''): void
    {
        $this->assertThat($event, new BroadcastSentTimes($times), $message);
    }

    /**
     * Assert a broadcast to a channel was sent a specific number of times
     *
     * @param string $channel Channel name
     * @param string $event Event name
     * @param int $times Expected number of times
     * @param string $message Optional assertion message
     * @return void
     */
    public function assertBroadcastToChannelTimes(
        string $channel,
        string $event,
        int $times,
        string $message = '',
    ): void {
        $broadcasts = TestBroadcaster::getBroadcastsToChannelWithEvent($channel, $event);
        $actualCount = count($broadcasts);

        $this->assertEquals(
            $times,
            $actualCount,
            $message ?: "Expected {$event} to be sent to {$channel} {$times} times, but was sent {$actualCount} times",
        );
    }

    /**
     * Get all captured broadcasts
     *
     * @return array<array<string, mixed>>
     */
    public function getBroadcasts(): array
    {
        return TestBroadcaster::getBroadcasts();
    }

    /**
     * Get broadcasts of a specific event
     *
     * @param string $event Event name
     * @return array<array<string, mixed>>
     */
    public function getBroadcastsByEvent(string $event): array
    {
        return TestBroadcaster::getBroadcastsByEvent($event);
    }

    /**
     * Get broadcasts to a specific channel
     *
     * @param string $channel Channel name
     * @return array<array<string, mixed>>
     */
    public function getBroadcastsToChannel(string $channel): array
    {
        return TestBroadcaster::getBroadcastsToChannel($channel);
    }

    /**
     * Get broadcasts via a specific connection
     *
     * @param string $connection Connection name
     * @return array<array<string, mixed>>
     */
    public function getBroadcastsByConnection(string $connection): array
    {
        return TestBroadcaster::getBroadcastsByConnection($connection);
    }
}
