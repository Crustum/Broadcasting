<?php
declare(strict_types=1);

namespace TestApp\Broadcasting;

use Cake\Datasource\EntityInterface;
use Crustum\Broadcasting\Channel\ChannelInterface;

class UnauthorizedChannel implements ChannelInterface
{
    public function join(EntityInterface $user, EntityInterface $model): array|bool
    {
        return false;
    }
}
