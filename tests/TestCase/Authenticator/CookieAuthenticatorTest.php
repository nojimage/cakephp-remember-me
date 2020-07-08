<?php

namespace RememberMe\Test\TestCase\Authenticator;

use Authentication\Authenticator\Result;
use Authentication\Identifier\IdentifierCollection;
use Cake\Datasource\EntityInterface;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\I18n\FrozenTime;
use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RememberMe\Authenticator\CookieAuthenticator;
use RememberMe\Model\Entity\RememberMeToken;
use RememberMe\Model\Table\RememberMeTokensTable;
use RememberMe\Test\TestCase\RememberMeTestCase as TestCase;

class CookieAuthenticatorTest extends TestCase
{
    /**
     * @var RememberMeTokensTable
     */
    private $Tokens;

    public function setUp()
    {
        parent::setUp();
        $this->Tokens = TableRegistry::getTableLocator()->get('RememberMe.RememberMeTokens');
    }

    /**
     * @return void
     */
    public function tearDown()
    {
        FrozenTime::setTestNow();
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testAuthenticateCredentialsNotPresent()
    {
        $identifiers = new IdentifierCollection([
            'RememberMe.RememberMeToken' => [
                'resolver' => [
                    'className' => 'Authentication.Orm',
                    'userModel' => 'AuthUsers',
                ],
            ],
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath']
        );
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifiers);
        $result = $authenticator->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::FAILURE_CREDENTIALS_MISSING, $result->getStatus());
    }

    /**
     * @return void
     */
    public function testAuthenticateEmptyCookie()
    {
        $identifiers = new IdentifierCollection([
            'RememberMe.RememberMeToken' => [
                'resolver' => [
                    'className' => 'Authentication.Orm',
                    'userModel' => 'AuthUsers',
                ],
            ],
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'rememberMe' => '',
            ]
        );
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifiers);
        $result = $authenticator->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::FAILURE_CREDENTIALS_INVALID, $result->getStatus());
    }

    /**
     * @return void
     */
    public function testAuthenticateUnencryptedCookie()
    {
        $identifiers = new IdentifierCollection([
            'RememberMe.RememberMeToken' => [
                'resolver' => [
                    'className' => 'Authentication.Orm',
                    'userModel' => 'AuthUsers',
                ],
            ],
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'rememberMe' => 'unencrypted',
            ]
        );
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifiers);
        $result = $authenticator->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::FAILURE_CREDENTIALS_INVALID, $result->getStatus());
        $this->assertSame(['Cookie token is invalid'], $result->getErrors());
    }

    /**
     * @return void
     */
    public function testAuthenticateInvalidCookie()
    {
        $identifiers = new IdentifierCollection([
            'RememberMe.RememberMeToken' => [
                'resolver' => [
                    'className' => 'Authentication.Orm',
                    'userModel' => 'AuthUsers',
                ],
            ],
        ]);

        $encryptedToken = CookieAuthenticator::encryptToken('foo', 'series_foo_1', 'logintoken2');
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'rememberMe' => $encryptedToken,
            ]
        );
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifiers);
        $result = $authenticator->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::FAILURE_IDENTITY_NOT_FOUND, $result->getStatus());
        $this->assertSame(['RememberMeToken' => ['token does not match']], $result->getErrors());
    }

    /**
     * @return void
     */
    public function testAuthenticateExpired()
    {
        FrozenTime::setTestNow('2017-10-01 11:22:34');
        $identifiers = new IdentifierCollection([
            'RememberMe.RememberMeToken' => [
                'resolver' => [
                    'className' => 'Authentication.Orm',
                    'userModel' => 'AuthUsers',
                ],
            ],
        ]);

        $encryptedToken = CookieAuthenticator::encryptToken('foo', 'series_foo_1', 'logintoken1');
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'rememberMe' => $encryptedToken,
            ]
        );
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifiers);
        $result = $authenticator->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::FAILURE_IDENTITY_NOT_FOUND, $result->getStatus());
        $this->assertSame(['RememberMeToken' => ['token expired']], $result->getErrors());
    }

    /**
     * @return void
     */
    public function testAuthenticateValid()
    {
        FrozenTime::setTestNow('2017-10-01 11:22:33');
        $identifiers = new IdentifierCollection([
            'RememberMe.RememberMeToken' => [
                'resolver' => [
                    'className' => 'Authentication.Orm',
                    'userModel' => 'AuthUsers',
                ],
            ],
        ]);

        $encryptedToken = CookieAuthenticator::encryptToken('foo', 'series_foo_1', 'logintoken1');
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'rememberMe' => $encryptedToken,
            ]
        );
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifiers);
        $result = $authenticator->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::SUCCESS, $result->getStatus());
        $this->assertInstanceOf(EntityInterface::class, $result->getData());
        $this->assertSame('foo', $result->getData()['username']);
    }

    /**
     * @return void
     */
    public function testPersistIdentity()
    {
        $identifiers = new IdentifierCollection([
            'Authentication.Password',
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath']
        );
        $request = $request->withParsedBody([
            'remember_me' => 1,
        ]);
        $response = new Response();
        $identity = new Entity([
            'id' => 1,
            'username' => 'foo',
        ]);
        $identity->setSource('AuthUsers');

        $authenticator = new CookieAuthenticator($identifiers);

        $result = $authenticator->persistIdentity($request, $response, $identity);

        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);

        $token = $this->Tokens->find()->orderDesc('id')->first();
        /* @var $token RememberMeToken */
        $this->assertSame('AuthUsers', $token->model);
        $this->assertSame('1', $token->foreign_id);

        $cookieHeader = $result['response']->getHeaderLine('Set-Cookie');
        $this->assertContains('rememberMe=', $cookieHeader);
        $this->assertContains('expires=' . $token->expires->setTimezone('GMT')->format(Cookie::EXPIRES_FORMAT), $cookieHeader);
        $encrypted = preg_replace('/\ArememberMe=(.+?);.*/', '$1', $cookieHeader);
        $decoded = CookieAuthenticator::decodeCookie(rawurldecode($encrypted));
        $this->assertSame($token->series, $decoded['series']);
        $this->assertSame($token->token, $decoded['token']);

        // Testing that the field is not present
        $request = $request->withParsedBody([]);
        $result = $authenticator->persistIdentity($request, $response, $identity);
        $this->assertNotContains('rememberMe', $result['response']->getHeaderLine('Set-Cookie'));

        // Testing a different field name
        $request = $request->withParsedBody([
            'other_field' => 1,
        ]);
        $authenticator = new CookieAuthenticator($identifiers, [
            'rememberMeField' => 'other_field',
        ]);
        $result = $authenticator->persistIdentity($request, $response, $identity);
        $this->assertContains('rememberMe=', $result['response']->getHeaderLine('Set-Cookie'));
    }

    /**
     * @return void
     */
    public function testDropExpiredTokenOnPersistIdentity()
    {
        $identifiers = new IdentifierCollection(['Authentication.Password']);
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath']
        );
        $request = $request->withParsedBody([
            'remember_me' => 1,
        ]);
        $response = new Response();
        $identity = new Entity([
            'id' => 1,
            'username' => 'foo',
        ]);
        $identity->setSource('AuthUsers');

        $this->assertCount(6, $this->Tokens->find()->all());

        $authenticator = new CookieAuthenticator($identifiers);

        $result = $authenticator->persistIdentity($request, $response, $identity);

        $this->assertInternalType('array', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);

        $cookieHeader = $result['response']->getHeaderLine('Set-Cookie');
        $this->assertContains('rememberMe=', $cookieHeader);

        $this->assertCount(1, $this->Tokens->find()->all(), 'then deleted expired tokens');
    }

    /**
     * @return void
     */
    public function testClearIdentity()
    {
        $identifiers = new IdentifierCollection([
            'RememberMe.RememberMeToken' => [
                'resolver' => [
                    'className' => 'Authentication.Orm',
                    'userModel' => 'AuthUsers',
                ],
            ],
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath']
        );
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifiers);

        $result = $authenticator->clearIdentity($request, $response);
        $this->assertInternalType('array', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);

        // Send http header that clear cookie.
        $this->assertEquals('rememberMe=; expires=Thu, 01-Jan-1970 00:00:01 UTC; path=/; secure; httponly', $result['response']->getHeaderLine('Set-Cookie'));
    }
}
