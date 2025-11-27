<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Channel;

use Cake\Datasource\EntityInterface;

/**
 * Channel Interface
 *
 * Defines the contract for channel authorization classes.
 * Channel classes must implement this interface to provide
 * authorization logic for private and presence channels.
 *
 * Example usage:
 *
 * ```php
 * class OrderChannel implements ChannelInterface
 * {
 *     public function join(User $user, Order $order): array|bool
 *     {
 *         return $user->id === $order->user_id;
 *     }
 * }
 * ```
 *
 * The join method should return:
 * - `true` or `false` for private channels
 * - An array with user information for presence channels
 * - `false` to deny access
 *
 * @package Crustum\Broadcasting\Channel
 */
interface ChannelInterface
{
    /**
     * Authenticate the user's access to the channel.
     *
     * This method determines whether the given user can access
     * the channel. The method signature can include additional
     * parameters that will be resolved from the channel name
     * using route model binding.
     *
     * For private channels, return true/false:
     * ```php
     * public function join(User $user): bool
     * {
     *     return $user->isActive();
     * }
     * ```
     *
     * For presence channels, return user data array:
     * ```php
     * public function join(User $user): array
     * {
     *     return [
     *         'id' => $user->id,
     *         'name' => $user->name,
     *         'avatar' => $user->avatar_url
     *     ];
     * }
     * ```
     *
     * With route model binding:
     * ```php
     * public function join(EntityInterface $user, EntityInterface $order): bool
     * {
     *     return $user->id === $order->user_id;
     * }
     * ```
     *
     * @param \Cake\Datasource\EntityInterface $user The authenticated user entity
     * @param \Cake\Datasource\EntityInterface $model Model entity from route binding (e.g., Order, Post, etc.)
     * @return array<string, mixed>|bool User data array for presence channels, boolean for private channels
     */
    public function join(EntityInterface $user, EntityInterface $model): array|bool;
}
