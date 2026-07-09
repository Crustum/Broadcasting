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
    public $table = 'rooms';

    /**
     * Fields
     *
     * @var array<string, mixed>
     */
    public $fields = [
        'id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'autoIncrement' => true, 'precision' => null],
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
            ],
        ];
        parent::init();
    }
}
