<?php
declare(strict_types=1);

namespace RememberMe\Model\Entity;

use Cake\ORM\Entity;

/**
 * RememberMeToken Entity
 *
 * @property int $id
 * @property \Cake\I18n\FrozenTime $created
 * @property \Cake\I18n\FrozenTime $modified
 * @property string $model
 * @property string $foreign_id
 * @property string $series
 * @property string $token
 * @property \Cake\I18n\FrozenTime $expires
 */
class RememberMeToken extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array
     */
    protected $_accessible = [
        '*' => true,
        'id' => false,
    ];

    /**
     * Fields that are excluded from JSON versions of the entity.
     *
     * @var array
     */
    protected $_hidden = [
        'token',
    ];
}
