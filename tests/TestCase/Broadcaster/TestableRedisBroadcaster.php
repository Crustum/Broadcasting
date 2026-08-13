<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Broadcaster;

use Crustum\Broadcasting\Broadcaster\RedisBroadcaster;

/**
 * Testable Redis Broadcaster
 *
 * Allows injecting a Redis client double for unit tests.
 */
class TestableRedisBroadcaster extends RedisBroadcaster
{
    /**
     * Injected Redis client.
     *
     * @var mixed
     */
    protected mixed $testRedisClient = null;

    /**
     * Constructor.
     *
     * @param array<string, mixed> $config Redis configuration
     * @param mixed $redisClient Optional Redis client double
     */
    public function __construct(array $config = [], mixed $redisClient = null)
    {
        $this->testRedisClient = $redisClient;
        parent::__construct($config);
    }

    /**
     * Set test Redis client.
     *
     * @param mixed $client Redis client double
     * @return void
     */
    public function setTestRedisClient(mixed $client): void
    {
        $this->testRedisClient = $client;
        $this->redis = $client;
    }

    /**
     * Get Redis connection.
     */
    protected function getRedisConnection(): mixed
    {
        if ($this->testRedisClient !== null) {
            return $this->testRedisClient;
        }

        return parent::getRedisConnection();
    }
}
