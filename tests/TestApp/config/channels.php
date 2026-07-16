<?php
/**
 * Channel authorization configuration for testing
 *
 * This file defines channel authorization rules used during testing.
 */

use Crustum\Broadcasting\Broadcasting;

Broadcasting::channel('private-test-channel', fn($user): bool => $user !== null);

Broadcasting::channel('private-test-{suffix}', fn($user, $suffix): bool => $user !== null);

Broadcasting::channel('presence-test-channel', function ($user): false|array {
    if ($user === null) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->username];
});

Broadcasting::channel('presence-test-{suffix}', function ($user, $suffix): false|array {
    if ($user === null) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->username];
});

Broadcasting::channel('private-restricted-{suffix}', fn($user, $suffix): false => false);

Broadcasting::channel('private-error-channel', function ($user): void {
    throw new Exception('Channel authorization error');
});

Broadcasting::channel('private-error-{suffix}', function ($user, $suffix): void {
    throw new Exception('Channel authorization error');
});
