<?php
declare(strict_types=1);

namespace Crustum\Broadcasting;

use Crustum\Broadcasting\Channel\Channel;
use Crustum\Broadcasting\Trait\SocketAwareTrait;
use RuntimeException;

/**
 * Pending Broadcast
 *
 * Fluent builder for broadcasting events.
 *
 * @package Crustum\Broadcasting
 */
class PendingBroadcast
{
    use SocketAwareTrait;

    /**
     * Channels to broadcast to.
     *
     * @var array<\Crustum\Broadcasting\Channel\Channel>
     */
    protected array $channels = [];

    /**
     * Event name.
     *
     * @var string|null
     */
    protected ?string $eventName = null;

    /**
     * Payload data.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Broadcasting connection name.
     *
     * @var string
     */
    protected string $connectionName = 'default';

    /**
     * Queue name for async broadcasting.
     *
     * @var string|null
     */
    protected ?string $queueName = null;

    /**
     * Delay in seconds before processing.
     *
     * @var int|null
     */
    protected ?int $delay = null;

    /**
     * Message expiration time in seconds.
     *
     * @var int|null
     */
    protected ?int $expires = null;

    /**
     * Message priority.
     *
     * @var string|null
     */
    protected ?string $priority = null;

    /**
     * Whether the broadcast was explicitly executed.
     *
     * @var bool
     */
    protected bool $executed = false;

    /**
     * Whether the broadcast should be skipped.
     *
     * @var bool
     */
    protected bool $skipped = false;

    /**
     * Constructor.
     *
     * @param \Crustum\Broadcasting\Channel\Channel|array<string|\Crustum\Broadcasting\Channel\Channel>|string $channels Channels
     */
    public function __construct(Channel|array|string $channels)
    {
        $this->channels = $this->normalizeChannels($channels);
    }

    /**
     * Set the event name.
     *
     * @param string $name Event name
     * @return $this
     */
    public function event(string $name)
    {
        $this->eventName = $name;

        return $this;
    }

    /**
     * Set the payload data.
     *
     * @param array<string, mixed> $data Payload data
     * @return $this
     */
    public function data(array $data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Set the broadcasting connection.
     *
     * @param string $name Connection name
     * @return $this
     */
    public function connection(string $name)
    {
        $this->connectionName = $name;

        return $this;
    }

    /**
     * Set the delay before processing (in seconds).
     *
     * @param int $delay Delay in seconds
     * @return $this
     */
    public function delay(int $delay)
    {
        $this->delay = $delay;

        return $this;
    }

    /**
     * Set the message expiration time (in seconds).
     *
     * @param int $expires Expiration time in seconds
     * @return $this
     */
    public function expires(int $expires)
    {
        $this->expires = $expires;

        return $this;
    }

    /**
     * Set the message priority.
     *
     * @param string $priority Priority constant from \Enqueue\Client\MessagePriority
     * @return $this
     */
    public function priority(string $priority)
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Exclude the current user from receiving the broadcast (alias for dontBroadcastToCurrentUser).
     *
     * @return $this
     */
    public function toOthers()
    {
        return $this->dontBroadcastToCurrentUser();
    }

    /**
     * Mark the broadcast to be skipped.
     *
     * @return $this
     */
    public function skip()
    {
        $this->skipped = true;
        $this->executed = true;

        return $this;
    }

    /**
     * Broadcast the event immediately.
     *
     * @return void
     */
    public function send(): void
    {
        $this->executed = true;

        if ($this->skipped) {
            return;
        }

        if (empty($this->eventName)) {
            throw new RuntimeException('Event name is required. Call event() before send().');
        }

        $channelNames = $this->getChannelNames();
        $payload = $this->data;
        if ($this->socket !== null) {
            $payload['socket'] = $this->socket;
        }

        Broadcasting::get($this->connectionName)->broadcast(
            $channelNames,
            $this->eventName,
            $payload,
        );
    }

    /**
     * Queue the broadcast for later execution.
     *
     * @param string|null $queueName Queue name
     * @return void
     */
    public function queue(?string $queueName = null): void
    {
        $this->executed = true;

        if ($this->skipped) {
            return;
        }

        if ($queueName !== null) {
            $this->queueName = $queueName;
        }

        if (empty($this->eventName)) {
            throw new RuntimeException('Event name is required. Call event() before queue().');
        }

        $channelNames = $this->getChannelNames();
        $payload = $this->data;

        if ($this->socket !== null) {
            $payload['socket'] = $this->socket;
        }

        $options = [];
        if ($this->queueName !== null) {
            $options['queue'] = $this->queueName;
        }
        if ($this->delay !== null) {
            $options['delay'] = $this->delay;
        }
        if ($this->expires !== null) {
            $options['expires'] = $this->expires;
        }
        if ($this->priority !== null) {
            $options['priority'] = $this->priority;
        }

        Broadcasting::queueBroadcast(
            $channelNames,
            $this->eventName,
            $payload,
            $this->connectionName,
            $options,
        );
    }

    /**
     * Auto-send in destructor if not explicitly executed.
     *
     * @return void
     */
    public function __destruct()
    {
        if (!$this->executed && $this->eventName !== null) {
            $this->send();
        }
    }

    /**
     * Normalize channels to array of Channel objects.
     *
     * @param \Crustum\Broadcasting\Channel\Channel|array<string|\Crustum\Broadcasting\Channel\Channel>|string $channels Channels
     * @return array<\Crustum\Broadcasting\Channel\Channel>
     */
    protected function normalizeChannels(Channel|array|string $channels): array
    {
        if ($channels instanceof Channel) {
            return [$channels];
        }

        if (is_string($channels)) {
            return [new Channel($channels)];
        }

        return array_map(function ($channel) {
            return $channel instanceof Channel ? $channel : new Channel($channel);
        }, $channels);
    }

    /**
     * Get channel names as strings.
     *
     * @return array<string>
     */
    protected function getChannelNames(): array
    {
        return array_map(function ($channel) {
            return $channel->getName();
        }, $this->channels);
    }
}
