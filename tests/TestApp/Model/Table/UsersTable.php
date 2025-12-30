<?php
declare(strict_types=1);

namespace TestApp\Model\Table;

use Cake\ORM\Table;
use Crustum\Broadcasting\Model\Interface\BroadcastingTraitInterface;
use Crustum\Broadcasting\Model\Trait\BroadcastingTrait;

/**
 * Users Table
 */
class UsersTable extends Table implements BroadcastingTraitInterface
{
    use BroadcastingTrait;

    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('users');
        $this->setDisplayField('username');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->hasMany('Posts', [
            'foreignKey' => 'user_id',
        ]);
    }
}
