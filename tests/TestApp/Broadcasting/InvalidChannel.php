<?php
declare(strict_types=1);

namespace TestApp\Broadcasting;

class InvalidChannel
{
    public function join(mixed $user, mixed $model): bool
    {
        return true;
    }
}
