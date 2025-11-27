<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Broadcaster;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Broadcaster Interface
 *
 * Defines the contract for broadcasting implementations.
 * Handles channel authentication and message broadcasting.
 * Follows CakePHP conventions with explicit getter/setter methods.
 *
 * @package Crustum\Broadcasting\Broadcaster
 */
interface BroadcasterInterface
{
    /**
     * Authenticate a request for a channel.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @return mixed Authentication result
     */
    public function auth(ServerRequestInterface $request): mixed;

    /**
     * Resolve authenticated user for user authentication.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @return array{id: string|int, name?: string, email?: string, roles?: array<string>, permissions?: array<string>}|null User authentication data or null if not authenticated
     */
    public function resolveUserAuth(ServerRequestInterface $request): ?array;

    /**
     * Resolve authenticated user for user authentication.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @return array{id: string|int, name?: string, email?: string, roles?: array<string>, permissions?: array<string>}|null User authentication data or null if not authenticated
     */
    public function resolveAuthenticatedUser(ServerRequestInterface $request): ?array;

    /**
     * Validate and format authentication response.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @param mixed $result Authentication result
     * @return mixed Formatted authentication response
     */
    public function validAuthenticationResponse(ServerRequestInterface $request, mixed $result): mixed;

    /**
     * Broadcast a message to channels.
     *
     * @param array<string> $channels Channel names
     * @param string $event Event name
     * @param array<string, mixed> $payload Message payload
     * @return void
     */
    public function broadcast(array $channels, string $event, array $payload = []): void;

    /**
     * Get the broadcaster name.
     * Following CakePHP convention for explicit getter methods.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Set broadcaster configuration.
     * Following CakePHP convention for explicit setter methods.
     *
     * @param array<string, mixed> $config Configuration array
     * @return void
     */
    public function setConfig(array $config): void;

    /**
     * Get broadcaster configuration.
     * Following CakePHP convention for explicit getter methods.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array;

    /**
     * Check if broadcaster supports the given channel type.
     *
     * @param string $channelType Channel type
     * @return bool
     */
    public function supportsChannelType(string $channelType): bool;

    /**
     * Set the registered channel authenticators.
     *
     * @param array<string, callable|string> $channels
     * @return void
     */
    public function setChannelCallbacks(array $channels): void;

    /**
     * Set the registered channel options.
     *
     * @param array<string, array<string, mixed>> $options
     * @return void
     */
    public function setChannelOptions(array $options): void;

    /**
     * Register a channel authenticator.
     *
     * @param string $channel Channel pattern
     * @param callable|string $callback Authentication callback
     * @param array<string, mixed> $options Channel options
     * @return $this
     */
    public function registerChannel(string $channel, callable|string $callback, array $options = []);
}
