<?php

namespace RememberMe\Test\TestCase\Auth;

use Cake\Controller\ComponentRegistry;
use Cake\Core\Plugin;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\I18n\Time;
use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Cake\Utility\Security;
use RememberMe\Auth\CookieAuthenticate;

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
    public $fixtures = ['plugin.remember_me.auth_users'];

    /**
     * @var ComponentRegistry
     */
    private $Collection;

    /**
     * @var CookieAuthenticate
     */
    private $auth;

    /**
     * @var \Cake\Http\Response
     */
    private $response;

    /**
     * @var string
     */
    private $salt;

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
        $token = password_hash('logintoken', PASSWORD_DEFAULT);

        TableRegistry::clear();
        $Users = TableRegistry::get('AuthUsers');
        $Users->updateAll(['password' => $password, 'login_cookie' => $token], []);

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
        Security::salt($this->salt);
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
            'fields' => ['username' => 'user', 'token' => 'login_token']
        ]);
        $this->assertEquals('AuthUsers', $object->getConfig('userModel'));
        $this->assertEquals(['username' => 'user', 'token' => 'login_token', 'password' => 'password'], $object->getConfig('fields'));
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
        $request = new ServerRequest('posts/index');
        $cookies = [
            'rememberMe' => $this->auth->encryptToken('bar', 'logintoken'),
        ];
        $request = $request->withCookieParams($cookies);
        $result = $this->auth->authenticate($request, $this->response);

        $expected = [
            'id' => 2,
            'username' => 'bar',
        ];
        $this->assertEquals($expected, $result);
    }

    public function testDecodeCookie()
    {
        $encoded = $this->auth->encryptToken('foo', '123456');
        $result = $this->auth->decodeCookie($encoded);
        $this->assertSame(['username' => 'foo', 'token' => '123456'], $result);
    }

    public function testSetLoginTokenToCookie()
    {
        $user = ['id' => 1, 'username' => 'foo'];
        $response = $this->auth->setLoginTokenToCookie(new Response(), $user);

        $this->assertNotEmpty($response->getCookie('rememberMe'));

        $decode = $this->auth->decodeCookie($response->getCookie('rememberMe')['value']);
        $this->assertSame('foo', $decode['username']);
        $this->assertArrayHasKey('token', $decode);
    }
}
