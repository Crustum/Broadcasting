<?php
declare(strict_types=1);

namespace TestApp\Model\Entity;

use Cake\ORM\Entity;

/**
 * Order Entity for Testing
 *
 * @property int $id
 * @property int $user_id
 * @property float $total
 * @property string $status
 * @property \Cake\I18n\FrozenTime|null $created
 * @property \Cake\I18n\FrozenTime|null $modified
 */
class Order extends Entity
{
    /**
     * Fields that can be mass assigned
     *
     * @var array<string, bool>
     */
    protected $_accessible = [
        'user_id' => true,
        'total' => true,
        'status' => true,
        'created' => true,
        'modified' => true,
    ];
}
