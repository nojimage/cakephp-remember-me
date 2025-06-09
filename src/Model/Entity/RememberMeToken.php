<?php
declare(strict_types=1);

namespace RememberMe\Model\Entity;

use Cake\ORM\Entity;

/**
 * RememberMeToken Entity
 *
 * @property int $id
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property string $model
 * @property string $foreign_id
 * @property string $series
 * @property string $token
 * @property \Cake\I18n\DateTime $expires
 */
class RememberMeToken extends Entity
{
    /**
     * @inheritDoc
     */
    protected array $_accessible = [
        '*' => true,
        'id' => false,
    ];

    /**
     * @inheritDoc
     */
    protected array $_hidden = [
        'token',
    ];
}
