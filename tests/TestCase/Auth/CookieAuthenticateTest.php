<?php

namespace RememberMe\Test\TestCase\Auth;

use Cake\Controller\ComponentRegistry;
use Cake\Controller\Component\AuthComponent;
use Cake\Event\Event;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\I18n\FrozenTime;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Cake\Utility\Hash;
use RememberMe\Auth\CookieAuthenticate;
use RememberMe\Compat\Security;
use RememberMe\Model\Table\RememberMeTokensTable;
use RememberMe\Test\Model\Table\AuthUsersTable;

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
        $this->Users = TableRegistry::get('AuthUsers', ['className' => AuthUsersTable::class]);
        $this->Users->updateAll(['password' => $password], []);
        // set tokens
        $this->Tokens = TableRegistry::get('RememberMe.RememberMeTokens');
        $this->Tokens->updateAll(['token' => 'logintoken'], []);

        $this->response = $this->getMockBuilder(Response::class)->getMock();
        $this->salt = Security::getSalt();
        Security::setSalt('DYhG93b0qyJfIxfs2guVoUubWwvniR2G0FgaC9mi');
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
        Security::setSalt($this->salt);
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
        $expectedArray = Hash::flatten($expected);
        $resultArray = array_intersect_key(Hash::flatten($result), $expectedArray);
        $this->assertEquals($expectedArray, $resultArray);
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
                'model' => 'AuthUsers',
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
                'model' => 'AuthUsers',
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
                'model' => 'AuthUsers',
                'foreign_id' => 2,
            ])->all();
        $this->assertCount(2, $user2Tokens);
    }

    /**
     * test authenticate with custom finder
     *
     * @return void
     * @link https://github.com/nojimage/cakephp-remember-me/issues/1
     */
    public function testAuthenticateWithFinder()
    {
        $this->auth->setConfig('finder', 'onlyUsername');

        FrozenTime::setTestNow('2017-09-01 12:23:34');
        $request = new ServerRequest('posts/index');
        $cookies = [
            'rememberMe' => $this->auth->encryptToken('bar', 'series_bar_1', 'logintoken'),
        ];
        $request = $request->withCookieParams($cookies);
        $result = $this->auth->authenticate($request, $this->response);

        $expected = [
            'username' => 'bar',
            'remember_me_token' => [
                'id' => 3,
                'series' => 'series_bar_1',
            ],
        ];
        $expectedArray = Hash::flatten($expected);
        $resultArray = array_intersect_key(Hash::flatten($result), $expectedArray);
        $this->assertEquals($expectedArray, $resultArray);
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
     * test for 'Auth.afterIdentify' event
     */
    public function testOnAfterIdentify()
    {
        // -- prepare
        FrozenTime::setTestNow('2017-08-01 12:23:34');
        $user = ['id' => 1, 'username' => 'foo'];
        $request = (new ServerRequest)->withData('remember_me', true);
        $response = (new Response());

        $subject = $this->getMockBuilder(AuthComponent::class)
            ->setConstructorArgs([$this->Collection])
            ->getMock();
        $subject->request = $request;
        $subject->response = $response;
        $event = new Event('Auth.afterIdentify', $subject);

        // -- run
        $result = $this->auth->onAfterIdentify($event, $user);

        // -- assertion
        $this->assertSame(7, Hash::get($result, 'remember_me_token.id'), 'check override user data');

        $this->assertNotEmpty($subject->response->getCookie('rememberMe'));

        $decode = $this->auth->decodeCookie($subject->response->getCookie('rememberMe')['value']);
        $this->assertSame('foo', $decode['username']);
        $this->assertArrayHasKey('token', $decode);
        $this->assertArrayHasKey('series', $decode);

        // check saved data
        $tokens = $this->Tokens->find()->where([
                'model' => 'AuthUsers',
                'foreign_id' => 1,
            ])
            ->orderDesc('modified')
            ->all();
        $this->assertCount(3, $tokens);

        $this->assertSame($decode['series'], $tokens->first()->series);
        $this->assertSame($decode['token'], $tokens->first()->token);
        $this->assertTrue($tokens->first()->expires->eq(new FrozenTime('2017-08-31 12:23:34')), 'default expires is 30days after');
    }

    /**
     * test for 'Auth.afterIdentify' event when token exists
     */
    public function testOnAfterIdentifyWhenTokenExists()
    {
        // -- prepare
        FrozenTime::setTestNow('2017-08-01 12:23:34');
        $user = [
            'id' => 1,
            'username' => 'foo',
            'remember_me_token' => [
                'id' => 2,
            ],
        ];
        $request = (new ServerRequest)->withData('remember_me', true);
        $response = (new Response());

        $subject = $this->getMockBuilder(AuthComponent::class)
            ->setConstructorArgs([$this->Collection])
            ->getMock();
        $subject->request = $request;
        $subject->response = $response;
        $event = new Event('Auth.afterIdentify', $subject);

        // -- run
        $result = $this->auth->onAfterIdentify($event, $user);

        // -- assertion
        $this->assertSame(2, Hash::get($result, 'remember_me_token.id'), 'check override user data');

        $this->assertNotEmpty($subject->response->getCookie('rememberMe'));

        $decode = $this->auth->decodeCookie($subject->response->getCookie('rememberMe')['value']);
        $this->assertSame('foo', $decode['username']);
        $this->assertArrayHasKey('token', $decode);
        $this->assertArrayHasKey('series', $decode);

        // saved to table
        $tokens = $this->Tokens->find()->where([
                'model' => 'AuthUsers',
                'foreign_id' => 1,
            ])->all();
        $this->assertCount(2, $tokens);

        $this->assertSame('series_foo_2', $tokens->last()->series);
        $this->assertSame($decode['token'], $tokens->last()->token);
        $this->assertTrue($tokens->last()->expires->eq(new FrozenTime('2017-08-31 12:23:34')), 'default expires is 30days after');
    }

    /**
     * test for 'Auth.afterIdentify' event
     */
    public function testDropExporedTokensOnAfterIdentify()
    {
        // -- prepare
        FrozenTime::setTestNow('2017-10-01 12:23:34');
        $user = ['id' => 1, 'username' => 'foo'];
        $request = (new ServerRequest)->withData('remember_me', true);
        $response = (new Response());

        $subject = $this->getMockBuilder(AuthComponent::class)
            ->setConstructorArgs([$this->Collection])
            ->getMock();
        $subject->request = $request;
        $subject->response = $response;
        $event = new Event('Auth.afterIdentify', $subject);

        // -- run
        $result = $this->auth->onAfterIdentify($event, $user);

        // -- assertion
        $this->assertCount(4, $this->Tokens->find()->all(), 'drop expired token');
    }

    /**
     * test for 'Auth.logout' event
     */
    public function testOnLogout()
    {
        $user = [
            'id' => 1,
            'username' => 'bar',
            'remember_me_token' => [
                'id' => 2,
            ],
        ];

        // set login cookie
        $response = (new Response())->withCookie('rememberMe', 'dummy');

        // test logout
        $subject = $this->getMockBuilder(AuthComponent::class)
            ->setConstructorArgs([$this->Collection])
            ->getMock();
        $subject->response = $response;
        $event = new Event('Auth.logout', $subject);

        $this->assertTrue($this->auth->onLogout($event, $user));

        $cookie = $subject->response->getCookie('rememberMe');
        $this->assertEmpty($cookie['value'], 'clear cookie values');

        $tokens = $this->Tokens->find()->where([
                'model' => 'AuthUsers',
                'foreign_id' => 1,
            ])->all();
        $this->assertCount(1, $tokens, 'drop token');
    }

    /**
     * test with EncryptedCookieMiddleware
     */
    public function testWorkWithEncryptedCookieMiddleware()
    {
        if (!class_exists('\Cake\Http\Middleware\EncryptedCookieMiddleware')) {
            $this->markTestSkipped();
            return;
        }

        $middleware = new \Cake\Http\Middleware\EncryptedCookieMiddleware(['rememberMe'], str_repeat('1234abcd', 4));
        $request = new ServerRequest();
        $response = new Response();

        $encoded = $this->auth->encryptToken('foo', 'series_foo_1', '123456');

        $response = $response->withCookie('rememberMe', ['value' => $encoded]);
        $response = $middleware($request, $response, function ($request, $response) {
            return $response;
        });

        $request = $request->withCookieCollection($response->getCookieCollection());
        $decryptRequest = null;
        /* @var $decryptRequest ServerRequest */
        $middleware($request, $response, function ($request, $response) use (&$decryptRequest) {
            $decryptRequest = $request;
            return $response;
        });

        $result = $this->auth->decodeCookie($decryptRequest->getCookie('rememberMe'));
        $this->assertSame(['username' => 'foo', 'series' => 'series_foo_1', 'token' => '123456'], $result);
    }
}
