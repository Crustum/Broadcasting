<?php
declare(strict_types=1);

namespace TestApp\Broadcasting;

class InvalidChannel
{
    public function join($user, $model): bool
    {
        return true;
    }
}
