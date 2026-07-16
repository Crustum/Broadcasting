<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestApp\Event;

use Crustum\Broadcasting\Channel\Channel;
use Crustum\Broadcasting\Event\BroadcastableInterface;
use Crustum\Broadcasting\Event\ConditionalInterface;
use Crustum\Broadcasting\Event\QueueableInterface;

class TestBroadcastableClass implements BroadcastableInterface, ConditionalInterface, QueueableInterface
{
    /**
     * Channels array
     *
     * @var array<\Crustum\Broadcasting\Channel\Channel>
     */
    protected array $channels = [];

    protected ?string $eventName = 'test.event';

    /**
     * Data array
     *
     * @var array<string, mixed>|null
     */
    protected ?array $data = [
        'test-key' => 'test-value',
        'test-number' => 123,
    ];

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

    /**
     * Set channels
     *
     * @param \Crustum\Broadcasting\Channel\Channel|array<\Crustum\Broadcasting\Channel\Channel>|string $channels Channels
     */
    public function setChannels(Channel|array|string $channels): static
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

    /**
     * Set data
     *
     * @param array<string, mixed>|null $data Data
     */
    public function setData(?array $data): static
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
