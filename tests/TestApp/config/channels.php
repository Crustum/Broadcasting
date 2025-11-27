<?php
/**
 * Channel authorization configuration for testing
 *
 * This file defines channel authorization rules used during testing.
 */

use Crustum\Broadcasting\Broadcasting;

Broadcasting::channel('private-test-channel', function ($user) {
    return $user !== null;
});

Broadcasting::channel('private-test-{suffix}', function ($user, $suffix) {
    return $user !== null;
});

Broadcasting::channel('presence-test-channel', function ($user) {
    if ($user === null) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->username];
});

Broadcasting::channel('presence-test-{suffix}', function ($user, $suffix) {
    if ($user === null) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->username];
});

Broadcasting::channel('private-restricted-{suffix}', function ($user, $suffix) {
    return false;
});

Broadcasting::channel('private-error-channel', function ($user) {
    throw new Exception('Channel authorization error');
});

Broadcasting::channel('private-error-{suffix}', function ($user, $suffix) {
    throw new Exception('Channel authorization error');
});
