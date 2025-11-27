<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestApp\Event;

use Crustum\Broadcasting\Channel\Channel;
use Crustum\Broadcasting\Event\BroadcastableInterface;
use Crustum\Broadcasting\Event\ConditionalInterface;
use Crustum\Broadcasting\Event\QueueableInterface;

class TestBroadcastableClass implements BroadcastableInterface, ConditionalInterface, QueueableInterface
{
    protected array $channels = [];
    protected ?string $eventName = null;
    protected ?array $data = null;
    protected ?string $socket = null;
    protected bool $shouldBroadcast = true;
    protected ?string $queue = 'high';
    protected ?int $delay = null;
    protected ?int $expires = null;
    protected ?string $priority = null;

    public function __construct()
    {
        $this->channels = [
            new Channel('test-channel-1'),
            new Channel('test-channel-2'),
        ];
        $this->eventName = 'test.event';
        $this->data = [
            'test-key' => 'test-value',
            'test-number' => 123,
        ];
    }

    public function broadcastEvent(): string
    {
        return $this->eventName ?? 'Test.event';
    }

    public function broadcastChannel(): Channel|array
    {
        return $this->channels;
    }

    public function broadcastSocket(): ?string
    {
        return $this->socket;
    }

    public function broadcastData(): ?array
    {
        return $this->data;
    }

    public function broadcastWhen(): bool
    {
        return $this->shouldBroadcast;
    }

    public function setChannels(Channel|array|string $channels): self
    {
        if (is_string($channels)) {
            $this->channels = [new Channel($channels)];
        } elseif ($channels instanceof Channel) {
            $this->channels = [$channels];
        } else {
            $this->channels = $channels;
        }

        return $this;
    }

    public function setEventName(?string $name): self
    {
        $this->eventName = $name;

        return $this;
    }

    public function setData(?array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function setShouldBroadcast(bool $shouldBroadcast): self
    {
        $this->shouldBroadcast = $shouldBroadcast;

        return $this;
    }

    public function setSocket(?string $socket): self
    {
        $this->socket = $socket;

        return $this;
    }

    public function broadcastQueue(): ?string
    {
        return $this->queue;
    }

    public function broadcastDelay(): ?int
    {
        return $this->delay;
    }

    public function broadcastExpires(): ?int
    {
        return $this->expires;
    }

    public function broadcastPriority(): ?string
    {
        return $this->priority;
    }

    public function setQueue(?string $queue): self
    {
        $this->queue = $queue;

        return $this;
    }

    public function setDelay(?int $delay): self
    {
        $this->delay = $delay;

        return $this;
    }

    public function setExpires(?int $expires): self
    {
        $this->expires = $expires;

        return $this;
    }

    public function setPriority(?string $priority): self
    {
        $this->priority = $priority;

        return $this;
    }
}
