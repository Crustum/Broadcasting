<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Trait;

use Cake\Routing\Router;

/**
 * Interacts With Sockets Trait
 *
 * Manages socket IDs for broadcasting exclusion.
 *
 * @package Crustum\Broadcasting\Trait
 */
trait SocketAwareTrait
{
    /**
     * The socket ID for the user that raised the event.
     *
     * @var string|null
     */
    protected ?string $socket = null;

    /**
     * Exclude the current user from receiving the broadcast.
     *
     * @return $this
     */
    public function dontBroadcastToCurrentUser()
    {
        $request = Router::getRequest();
        if ($request !== null) {
            $socketId = $request->getHeaderLine('X-Socket-ID');
            if (empty($socketId)) {
                $socketId = $request->getQuery('socket_id');
            }
            $this->socket = $socketId ?: null;
        }

        return $this;
    }

    /**
     * Broadcast the event to everyone.
     *
     * @return $this
     */
    public function broadcastToEveryone()
    {
        $this->socket = null;

        return $this;
    }

    /**
     * Get the socket ID.
     *
     * @return string|null
     */
    public function getSocket(): ?string
    {
        return $this->socket;
    }

    /**
     * Set the socket ID.
     *
     * @param string|null $socket Socket ID
     * @return $this
     */
    public function setSocket(?string $socket)
    {
        $this->socket = $socket;

        return $this;
    }
}
