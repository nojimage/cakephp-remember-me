<?php
declare(strict_types=1);

namespace RememberMe\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * AuthUsersFixture
 */
class AuthUsersFixture extends TestFixture
{
    /**
     * Records
     *
     * @var array
     */
    public $records = [
        ['id' => 1, 'username' => 'foo', 'password' => 'not use'],
        ['id' => 2, 'username' => 'bar', 'password' => 'not use'],
        ['id' => 3, 'username' => 'boo', 'password' => 'not use'],
    ];
}
