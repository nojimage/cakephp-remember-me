<?php

namespace RememberMe\Test\TestCase;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use RememberMe\Test\Model\Table\AuthUsersTable;

class RememberMeTestCase extends TestCase
{
    public $fixtures = [
        'plugin.RememberMe.AuthUsers',
        'plugin.RememberMe.RememberMeTokens',
    ];

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        parent::setUp();
        $this->_setupUsersAndPasswords();
    }

    /**
     * _setupUsersAndPasswords
     *
     * @return void
     */
    protected function _setupUsersAndPasswords()
    {
        $password = password_hash('password', PASSWORD_DEFAULT);
        TableRegistry::getTableLocator()->clear();

        $AuthUsers = TableRegistry::getTableLocator()->get('AuthUsers', [
            'className' => AuthUsersTable::class,
        ]);
        $AuthUsers->updateAll(['password' => $password], []);
    }
}
