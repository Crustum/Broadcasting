<?php
declare(strict_types=1);

namespace TestApp\Broadcasting;

use Cake\Datasource\EntityInterface;
use Crustum\Broadcasting\Channel\ChannelInterface;

class TestPresenceChannel implements ChannelInterface
{
    public function join(EntityInterface $user, EntityInterface $model): array|bool
    {
        if ($user->get('id') !== $model->get('user_id')) {
            return false;
        }

        return [
            'id' => $user->get('id'),
            'name' => $user->get('name'),
            'email' => $user->get('email'),
        ];
    }
}
