<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Broadcaster;

use Crustum\Broadcasting\Broadcaster\MercureBroadcaster;

/**
 * Capturing Mercure Broadcaster
 *
 * Test helper that captures publishes instead of sending them, so dispatch
 * decisions can be asserted without a reachable Mercure hub.
 */
class CapturingMercureBroadcaster extends MercureBroadcaster
{
    /**
     * Captured publishes.
     *
     * @var list<array{channels: array<string>, event: string, payload: array<string, mixed>}>
     */
    public array $sent = [];

    /**
     * Capture a publish instead of sending it.
     *
     * @param array<string> $channels Channel names
     * @param string $event Event name
     * @param array<string, mixed> $payload Message payload
     * @return void
     */
    public function broadcast(array $channels, string $event, array $payload = []): void
    {
        $this->sent[] = [
            'channels' => $channels,
            'event' => $event,
            'payload' => $payload,
        ];
    }
}
