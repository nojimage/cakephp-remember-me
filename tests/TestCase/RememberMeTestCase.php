<?php
declare(strict_types=1);

namespace RememberMe\Test\TestCase;

use Cake\TestSuite\TestCase;
use Cake\Utility\Security;
use TestApp\Model\Table\AuthUsersTable;

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
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->_setupUsersAndPasswords();

        $this->salt = Security::getSalt();
        Security::setSalt('DYhG93b0qyJfIxfs2guVoUubWwvniR2G0FgaC9mi');
    }

    public function tearDown(): void
    {
        Security::setSalt($this->salt);
        parent::tearDown();
    }

    /**
     * _setupUsersAndPasswords
     *
     * @return void
     */
    protected function _setupUsersAndPasswords(): void
    {
        $password = password_hash('12345678', PASSWORD_DEFAULT);
        $this->getTableLocator()->clear();

        $Users = $this->getTableLocator()->get('Users');
        $Users->updateAll(['password' => $password], []);

        $AuthUsers = $this->getTableLocator()->get('AuthUsers', [
            'className' => AuthUsersTable::class,
        ]);
        $AuthUsers->updateAll(['password' => $password], []);
    }
}
