<?php

namespace RememberMe\Test\TestCase\Auth;

use Cake\Controller\ComponentRegistry;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\I18n\FrozenTime;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Cake\Utility\Security;
use RememberMe\Auth\CookieAuthenticate;
use RememberMe\Model\Table\RememberMeTokensTable;

/**
 * Test case for CookieAuthenticate
 */
class CookieAuthenticateTest extends TestCase
{

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'plugin.RememberMe.AuthUsers',
        'plugin.RememberMe.RememberMeTokens',
    ];

    /**
     * @var ComponentRegistry
     */
    private $Collection;

    /**
     * @var CookieAuthenticate
     */
    private $auth;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var string
     */
    private $salt;

    /**
     *
     * @var RememberMeTokensTable
     */
    private $Tokens;

    /**
     *
     * @var Table
     */
    private $Users;

    /**
     * setup
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->Collection = $this->getMockBuilder(ComponentRegistry::class)->getMock();
        $this->auth = new CookieAuthenticate($this->Collection, [
            'userModel' => 'AuthUsers'
        ]);
        $password = password_hash('password', PASSWORD_DEFAULT);

        TableRegistry::clear();
        // set password
        $this->Users = TableRegistry::get('AuthUsers');
        $this->Users->updateAll(['password' => $password], []);
        // set tokens
        $this->Tokens = TableRegistry::get('RememberMe.RememberMeTokens');
        $this->Tokens->updateAll(['token' => 'logintoken'], []);

        $this->response = $this->getMockBuilder(Response::class)->getMock();
        $this->salt = Security::salt();
        Security::salt('DYhG93b0qyJfIxfs2guVoUubWwvniR2G0FgaC9mi');
    }

    /**
     * tearDown
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->Users);
        unset($this->Tokens);
        Security::salt($this->salt);
        FrozenTime::setTestNow();
        parent::tearDown();
    }

    /**
     * test applying settings in the constructor
     *
     * @return void
     */
    public function testConstructor()
    {
        $object = new CookieAuthenticate($this->Collection, [
            'userModel' => 'AuthUsers',
            'fields' => ['username' => 'user']
        ]);
        $this->assertEquals('AuthUsers', $object->getConfig('userModel'));
        $this->assertEquals(['username' => 'user', 'password' => 'password'], $object->getConfig('fields'));
    }

    /**
     * test the authenticate method
     *
     * @return void
     */
    public function testAuthenticateNoCookie()
    {
        $request = new ServerRequest('posts/index');
        $this->assertFalse($this->auth->authenticate($request, $this->response));
    }

    /**
     * test authenticate success
     *
     * @return void
     */
    public function testAuthenticateSuccess()
    {
        FrozenTime::setTestNow('2017-09-01 12:23:34');
        $request = new ServerRequest('posts/index');
        $cookies = [
            'rememberMe' => $this->auth->encryptToken('bar', 'series_bar_1', 'logintoken'),
        ];
        $request = $request->withCookieParams($cookies);
        $result = $this->auth->authenticate($request, $this->response);

        $expected = [
            'id' => 2,
            'username' => 'bar',
            'remember_me_token' => [
                'id' => 3,
                'series' => 'series_bar_1',
            ],
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * test authenticate failure (invalid token)
     *
     * @return void
     */
    public function testAuthenticateFailureWithInvalidToken()
    {
        FrozenTime::setTestNow('2017-09-01 12:23:34');
        $request = new ServerRequest('posts/index');
        $cookies = [
            'rememberMe' => $this->auth->encryptToken('bar', 'series_bar_1', 'invalid_token'),
        ];
        $request = $request->withCookieParams($cookies);
        $result = $this->auth->authenticate($request, $this->response);

        $this->assertFalse($result);

        $this->assertFalse($this->Tokens->exists([
                'series' => 'series_bar_1',
            ]), 'drop series_bar_1 token');

        $user2Tokens = $this->Tokens->find()->where([
                'table' => 'AuthUsers',
                'foreign_id' => 2,
            ])->all();
        $this->assertCount(1, $user2Tokens);
    }

    /**
     * test authenticate failure (expire token)
     *
     * @return void
     */
    public function testAuthenticateFailureWithExpireToken()
    {
        FrozenTime::setTestNow('2017-10-01 11:22:34');
        $request = new ServerRequest('posts/index');
        $cookies = [
            'rememberMe' => $this->auth->encryptToken('bar', 'series_bar_1', 'logintoken'),
        ];
        $request = $request->withCookieParams($cookies);
        $result = $this->auth->authenticate($request, $this->response);

        $this->assertFalse($result);

        $this->assertFalse($this->Tokens->exists([
                'series' => 'series_bar_1',
            ]), 'drop series_bar_1 token');

        $user2Tokens = $this->Tokens->find()->where([
                'table' => 'AuthUsers',
                'foreign_id' => 2,
            ])->all();
        $this->assertCount(1, $user2Tokens);
    }

    /**
     * test authenticate failure (invalid series)
     *
     * @return void
     */
    public function testAuthenticateFailureWithInvalidSeries()
    {
        FrozenTime::setTestNow('2017-09-01 12:23:34');
        $request = new ServerRequest('posts/index');
        $cookies = [
            'rememberMe' => $this->auth->encryptToken('bar', 'invalid_series', 'logintoken'),
        ];
        $request = $request->withCookieParams($cookies);
        $result = $this->auth->authenticate($request, $this->response);

        $this->assertFalse($result);

        $user2Tokens = $this->Tokens->find()->where([
                'table' => 'AuthUsers',
                'foreign_id' => 2,
            ])->all();
        $this->assertCount(2, $user2Tokens);
    }

    /**
     * test for decodeCookie
     */
    public function testDecodeCookie()
    {
        $encoded = $this->auth->encryptToken('foo', 'series_foo_1', '123456');
        $result = $this->auth->decodeCookie($encoded);
        $this->assertSame(['username' => 'foo', 'series' => 'series_foo_1', 'token' => '123456'], $result);
    }

    /**
     * test for setLoginTokenToCookie
     */
    public function testSetLoginTokenToCookie()
    {
        FrozenTime::setTestNow('2017-09-01 12:23:34');
        $user = ['id' => 1, 'username' => 'foo'];
        $response = $this->auth->setLoginTokenToCookie(new Response(), $user);

        $this->assertNotEmpty($response->getCookie('rememberMe'));

        $decode = $this->auth->decodeCookie($response->getCookie('rememberMe')['value']);
        $this->assertSame('foo', $decode['username']);
        $this->assertArrayHasKey('token', $decode);
        $this->assertArrayHasKey('series', $decode);

        // saved to table
        $tokens = $this->Tokens->find()->where([
                'table' => 'AuthUsers',
                'foreign_id' => 1,
            ])->all();
        $this->assertCount(3, $tokens);

        $this->assertSame($decode['series'], $tokens->last()->series);
        $this->assertSame($decode['token'], $tokens->last()->token);
        $this->assertSame('2017-10-01T12:23:34+09:00', $tokens->last()->expires->toIso8601String());
    }

    /**
     * test for setLoginTokenToCookie when token exists
     */
    public function testSetLoginTokenToCookieWhenTokenExists()
    {
        FrozenTime::setTestNow('2017-09-01 12:23:34');
        $user = [
            'id' => 1,
            'username' => 'foo',
            'remember_me_token' => [
                'id' => 2,
            ],
        ];
        $response = $this->auth->setLoginTokenToCookie(new Response(), $user);

        $this->assertNotEmpty($response->getCookie('rememberMe'));

        $decode = $this->auth->decodeCookie($response->getCookie('rememberMe')['value']);
        $this->assertSame('foo', $decode['username']);
        $this->assertArrayHasKey('token', $decode);
        $this->assertArrayHasKey('series', $decode);

        // saved to table
        $tokens = $this->Tokens->find()->where([
                'table' => 'AuthUsers',
                'foreign_id' => 1,
            ])->all();
        $this->assertCount(2, $tokens);

        $this->assertSame('series_foo_2', $tokens->last()->series);
        $this->assertSame($decode['token'], $tokens->last()->token);
        $this->assertSame('2017-10-01T12:23:34+09:00', $tokens->last()->expires->toIso8601String());
    }
}
