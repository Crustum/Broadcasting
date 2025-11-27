<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * OrdersFixture
 */
class OrdersFixture extends TestFixture
{
    /**
     * Records
     *
     * @var array<array<string, mixed>>
     */
    public array $records = [
        [
            'id' => 1,
            'user_id' => 1,
            'total' => 99.99,
            'status' => 'paid',
            'created' => '2024-01-01 00:00:00',
            'modified' => '2024-01-01 00:00:00',
        ],
        [
            'id' => 2,
            'user_id' => 2,
            'total' => 199.99,
            'status' => 'pending',
            'created' => '2024-01-02 00:00:00',
            'modified' => '2024-01-02 00:00:00',
        ],
    ];
}
