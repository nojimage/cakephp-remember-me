<?php

namespace RememberMe\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * RememberMeTokensFixture
 *
 */
class RememberMeTokensFixture extends TestFixture
{

    /**
     * Fields
     *
     * @var array
     */
    // @codingStandardsIgnoreStart
    public $fields = [
        'id' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'autoIncrement' => true, 'precision' => null],
        'created' => ['type' => 'timestamp', 'length' => null, 'null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '', 'precision' => null],
        'modified' => ['type' => 'timestamp', 'length' => null, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null],
        'model' => ['type' => 'string', 'length' => 64, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'fixed' => null],
        'foreign_id' => ['type' => 'string', 'length' => 36, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'fixed' => null],
        'series' => ['type' => 'string', 'length' => 64, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'fixed' => null],
        'token' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'fixed' => null],
        'expires' => ['type' => 'timestamp', 'length' => null, 'null' => false, 'default' => '0000-00-00 00:00:00', 'comment' => '', 'precision' => null],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id'], 'length' => []],
            'U_token_identifier' => ['type' => 'unique', 'columns' => ['model', 'foreign_id', 'series'], 'length' => []],
        ],
        '_options' => [
            'engine' => 'InnoDB',
        ],
    ];
    // @codingStandardsIgnoreEnd

    /**
     * Records
     *
     * @var array
     */
    public $records = [
        [
            'id' => 1,
            'created' => '2017-09-01 11:22:33',
            'modified' => '2017-09-01 11:22:33',
            'model' => 'AuthUsers',
            'foreign_id' => '1',
            'series' => 'series_foo_1',
            'token' => '',
            'expires' => '2017-10-01 11:22:33',
        ],
        [
            'id' => 2,
            'created' => '2017-09-01 11:22:33',
            'modified' => '2017-09-01 11:22:33',
            'model' => 'AuthUsers',
            'foreign_id' => '1',
            'series' => 'series_foo_2',
            'token' => '',
            'expires' => '2017-10-01 11:22:33',
        ],
        [
            'id' => 3,
            'created' => '2017-09-01 11:22:33',
            'modified' => '2017-09-01 11:22:33',
            'model' => 'AuthUsers',
            'foreign_id' => '2',
            'series' => 'series_bar_1',
            'token' => '',
            'expires' => '2017-10-01 11:22:33',
        ],
        [
            'id' => 4,
            'created' => '2017-09-01 11:22:33',
            'modified' => '2017-09-01 11:22:33',
            'model' => 'AuthUsers',
            'foreign_id' => '2',
            'series' => 'series_bar_2',
            'token' => '',
            'expires' => '2017-10-01 11:22:33',
        ],
        [
            'id' => 5,
            'created' => '2017-09-01 11:22:33',
            'modified' => '2017-09-01 11:22:33',
            'model' => 'AuthUsers',
            'foreign_id' => '3',
            'series' => 'series_boo_1',
            'token' => '',
            'expires' => '2017-10-01 11:22:33',
        ],
        [
            'id' => 6,
            'created' => '2017-09-01 11:22:33',
            'modified' => '2017-09-01 11:22:33',
            'model' => 'AuthUsers',
            'foreign_id' => '3',
            'series' => 'series_boo_2',
            'token' => '',
            'expires' => '2017-10-01 11:22:33',
        ],
    ];
}
