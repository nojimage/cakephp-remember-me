<?php

namespace RememberMe\Test\TestCase;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use RememberMe\Compat\Security;
use RememberMe\Test\Model\Table\AuthUsersTable;

abstract class RememberMeTestCase extends TestCase
{
    public $fixtures = [
        'core.Users',
        'plugin.RememberMe.AuthUsers',
        'plugin.RememberMe.RememberMeTokens',
    ];

    /**
     * @var string
     */
    private $salt;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        parent::setUp();
        $this->_setupUsersAndPasswords();

        $this->salt = Security::getSalt();
        Security::setSalt('DYhG93b0qyJfIxfs2guVoUubWwvniR2G0FgaC9mi');
    }

    public function tearDown()
    {
        Security::setSalt($this->salt);
        parent::tearDown();
    }

    /**
     * _setupUsersAndPasswords
     *
     * @return void
     */
    protected function _setupUsersAndPasswords()
    {
        $password = password_hash('12345678', PASSWORD_DEFAULT);
        TableRegistry::getTableLocator()->clear();

        $Users = TableRegistry::getTableLocator()->get('Users');
        $Users->updateAll(['password' => $password], []);

        $AuthUsers = TableRegistry::getTableLocator()->get('AuthUsers', [
            'className' => AuthUsersTable::class,
        ]);
        $AuthUsers->updateAll(['password' => $password], []);
    }
}
