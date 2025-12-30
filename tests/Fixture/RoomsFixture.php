<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Rooms Fixture
 *
 * Test fixture for rooms table.
 */
class RoomsFixture extends TestFixture
{
    /**
     * Table name
     *
     * @var string
     */
    public string $table = 'rooms';

    /**
     * Fields
     *
     * @var array<string, mixed>
     */
    public array $fields = [
        'id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'autoIncrement' => true, 'precision' => null],
        'user_id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id'], 'length' => []],
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
                'id' => 456,
                'user_id' => 1,
            ],
        ];
        parent::init();
    }
}
