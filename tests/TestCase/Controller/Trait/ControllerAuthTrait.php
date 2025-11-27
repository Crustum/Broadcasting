<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Controller\Trait;

use Cake\Datasource\EntityInterface;
use Cake\ORM\TableRegistry;

/**
 * Provides authentication helper methods for controller tests.
 */
trait ControllerAuthTrait
{
    /**
     * Authenticates a user by their ID for the test request.
     *
     * @param int $userId The ID of the user to authenticate as.
     * @return \Cake\Datasource\EntityInterface The authenticated user entity.
     */
    protected function loginAs(int $userId): EntityInterface
    {
        $users = TableRegistry::getTableLocator()->get('Users');
        $user = $users->get($userId);

        $this->session([
            'Auth' => $user,
        ]);

        return $user;
    }
}
