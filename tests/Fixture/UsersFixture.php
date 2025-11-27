<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * UsersFixture
 *
 * Test fixture for users table used in authentication testing.
 */
class UsersFixture extends TestFixture
{
    /**
     * Table name
     *
     * @var string
     */
    public string $table = 'users';

    /**
     * Fields
     *
     * @var array<string, mixed>
     */
    public array $fields = [
        'id' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'autoIncrement' => true, 'precision' => null],
        'username' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'collate' => 'utf8mb4_unicode_ci', 'comment' => '', 'precision' => null],
        'email' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'collate' => 'utf8mb4_unicode_ci', 'comment' => '', 'precision' => null],
        'password' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'collate' => 'utf8mb4_unicode_ci', 'comment' => '', 'precision' => null],
        'full_name' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null, 'collate' => 'utf8mb4_unicode_ci', 'comment' => '', 'precision' => null],
        'active' => ['type' => 'boolean', 'length' => null, 'null' => false, 'default' => '1', 'comment' => '', 'precision' => null],
        'created' => ['type' => 'datetime', 'length' => null, 'precision' => null, 'null' => true, 'default' => null, 'comment' => ''],
        'modified' => ['type' => 'datetime', 'length' => null, 'precision' => null, 'null' => true, 'default' => null, 'comment' => ''],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id'], 'length' => []],
            'users_email_unique' => ['type' => 'unique', 'columns' => ['email'], 'length' => []],
            'users_username_unique' => ['type' => 'unique', 'columns' => ['username'], 'length' => []],
        ],
        '_options' => [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ];

    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 123,
                'username' => 'testuser',
                'email' => 'test@example.com',
                'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                'full_name' => 'Test User',
                'active' => true,
                'created' => '2023-01-01 00:00:00',
                'modified' => '2023-01-01 00:00:00',
            ],
            [
                'id' => 456,
                'username' => 'adminuser',
                'email' => 'admin@example.com',
                'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                'full_name' => 'Admin User',
                'active' => true,
                'created' => '2023-01-01 00:00:00',
                'modified' => '2023-01-01 00:00:00',
            ],
            [
                'id' => 789,
                'username' => 'inactiveuser',
                'email' => 'inactive@example.com',
                'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                'full_name' => 'Inactive User',
                'active' => false,
                'created' => '2023-01-01 00:00:00',
                'modified' => '2023-01-01 00:00:00',
            ],
        ];
        parent::init();
    }
}
