<?php
declare(strict_types=1);

/**
 * Abstract schema for CakePHP tests.
 *
 * This format resembles the existing fixture schema
 * and is converted to SQL via the Schema generation
 * features of the Database package.
 */
return [
    'auth_users' => [
        'table' => 'auth_users',
        'columns' => [
            'id' => ['type' => 'integer', 'length' => 11, 'unsigned' => true, 'null' => false, 'default' => null, 'comment' => '', 'autoIncrement' => true, 'precision' => null],
            'username' => ['type' => 'string', 'length' => 190, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'fixed' => null],
            'password' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null, 'fixed' => null],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id'], 'length' => []],
            'U_username' => ['type' => 'unique', 'columns' => ['username'], 'length' => []],
        ],
    ],
    'users' => [
        'table' => 'users',
        'columns' => [
            'id' => ['type' => 'integer'],
            'username' => ['type' => 'string', 'null' => true],
            'password' => ['type' => 'string', 'null' => true],
            'created' => ['type' => 'timestamp', 'null' => true],
            'updated' => ['type' => 'timestamp', 'null' => true],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
    ],
];
