<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Broadcaster;

use Crustum\Broadcasting\Broadcaster\PusherBroadcaster;
use Pusher\Pusher;

/**
 * Testable Pusher Broadcaster
 *
 * Test helper class that allows injecting Pusher client for testing.
 */
class TestablePusherBroadcaster extends PusherBroadcaster
{
    /**
     * Test Pusher client to inject.
     *
     * @var \Pusher\Pusher|null
     */
    private ?Pusher $testPusherClient = null;

    /**
     * Set test Pusher client.
     *
     * @param \Pusher\Pusher $client Pusher client
     * @return void
     */
    public function setTestPusherClient(Pusher $client): void
    {
        $this->testPusherClient = $client;
    }

    /**
     * Create Pusher client instance.
     *
     * @param array{driver?: string, key: string, secret: string, app_id: string, options?: array<string, mixed>} $config Pusher configuration
     * @return \Pusher\Pusher
     */
    protected function createPusherClient(array $config): Pusher
    {
        if ($this->testPusherClient !== null) {
            return $this->testPusherClient;
        }

        return parent::createPusherClient($config);
    }
}
