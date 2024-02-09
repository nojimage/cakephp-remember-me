<?php
declare(strict_types=1);

namespace RememberMe\Test\TestCase\Authenticator;

use Authentication\Authenticator\Result;
use Authentication\Authenticator\ResultInterface;
use Authentication\Identifier\IdentifierCollection;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Http\Cookie\CookieInterface;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\I18n\FrozenTime;
use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RememberMe\Authenticator\CookieAuthenticator;
use RememberMe\Test\TestCase\RememberMeTestCase as TestCase;

class CookieAuthenticatorTest extends TestCase
{
    /**
     * @var RememberMeTokensTable
     */
    private $Tokens;

    public function setUp(): void
    {
        parent::setUp();
        $this->Tokens = TableRegistry::getTableLocator()->get('RememberMe.RememberMeTokens');
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
        FrozenTime::setTestNow();
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testAuthenticateCredentialsNotPresent(): void
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

        $authenticator = new CookieAuthenticator($identifiers);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(ResultInterface::FAILURE_CREDENTIALS_MISSING, $result->getStatus());
    }

    /**
     * @return void
     */
    public function testAuthenticateEmptyCookie(): void
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

        $authenticator = new CookieAuthenticator($identifiers);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(ResultInterface::FAILURE_CREDENTIALS_MISSING, $result->getStatus());
    }

    /**
     * @return void
     */
    public function testAuthenticateUnencryptedCookie(): void
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

        $authenticator = new CookieAuthenticator($identifiers);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(ResultInterface::FAILURE_CREDENTIALS_INVALID, $result->getStatus());
        $this->assertSame(['Cookie token is invalid', 'Can\'t decrypt cookie.'], $result->getErrors());
    }

    /**
     * @return void
     */
    public function testAuthenticateInvalidCookie(): void
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

        $authenticator = new CookieAuthenticator($identifiers);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(ResultInterface::FAILURE_IDENTITY_NOT_FOUND, $result->getStatus());
        $this->assertSame(['RememberMeToken' => ['token does not match']], $result->getErrors());
    }

    /**
     * @return void
     */
    public function testAuthenticateExpired(): void
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

        $authenticator = new CookieAuthenticator($identifiers);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(ResultInterface::FAILURE_IDENTITY_NOT_FOUND, $result->getStatus());
        $this->assertSame(['RememberMeToken' => ['token expired']], $result->getErrors());
    }

    /**
     * @return void
     */
    public function testAuthenticateValid(): void
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

        $authenticator = new CookieAuthenticator($identifiers);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(ResultInterface::SUCCESS, $result->getStatus());
        $this->assertInstanceOf(EntityInterface::class, $result->getData());
        $this->assertSame('foo', $result->getData()['username']);
    }

    /**
     * @return void
     */
    public function testPersistIdentity(): void
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

        $this->assertIsArray($result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);

        $token = $this->Tokens->find()->orderDesc('id')->first();
        /** @var RememberMeToken $token */
        $this->assertSame('AuthUsers', $token->model);
        $this->assertSame('1', $token->foreign_id);

        $cookieHeader = $result['response']->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('rememberMe=', $cookieHeader);
        $this->assertStringContainsString('expires=' . $token->expires->setTimezone('GMT')->format(CookieInterface::EXPIRES_FORMAT), $cookieHeader);
        $encrypted = preg_replace('/\ArememberMe=(.+?);.*/', '$1', $cookieHeader);
        $decoded = CookieAuthenticator::decodeCookie(rawurldecode($encrypted));
        $this->assertSame($token->series, $decoded['series']);
        $this->assertSame($token->token, $decoded['token']);

        // Testing that the field is not present
        $request = $request->withParsedBody([]);
        $result = $authenticator->persistIdentity($request, $response, $identity);
        $this->assertStringNotContainsString('rememberMe', $result['response']->getHeaderLine('Set-Cookie'));

        // Testing a different field name
        $request = $request->withParsedBody([
            'other_field' => 1,
        ]);
        $authenticator = new CookieAuthenticator($identifiers, [
            'rememberMeField' => 'other_field',
        ]);
        $result = $authenticator->persistIdentity($request, $response, $identity);
        $this->assertStringContainsString('rememberMe=', $result['response']->getHeaderLine('Set-Cookie'));
    }

    /**
     * @return void
     */
    public function testPersistIdentityCanTwice(): void
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

        $authenticator->persistIdentity($request, $response, $identity);
        $authenticator->persistIdentity($request, $response, $identity);

        $token = $this->Tokens->find()->orderDesc('id')->first();
        /** @var RememberMeToken $token */
        $this->assertSame('AuthUsers', $token->model);
        $this->assertSame('1', $token->foreign_id);

        $this->assertCount(2, $this->Tokens->find()->all());
    }

    /**
     * @return void
     */
    public function testDropExpiredTokenOnPersistIdentity(): void
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

        $this->assertIsArray($result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);

        $cookieHeader = $result['response']->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('rememberMe=', $cookieHeader);

        $this->assertCount(1, $this->Tokens->find()->all(), 'then deleted expired tokens');
    }

    /**
     * @return void
     */
    public function testClearIdentity(): void
    {
        $identifiers = new IdentifierCollection([
            'RememberMe.RememberMeToken',
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath']
        );
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifiers);

        $result = $authenticator->clearIdentity($request, $response);
        $this->assertIsArray($result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);

        // Send http header that clear cookie.
        $expectsCookie = version_compare(Configure::version(), '4.4.12', '<')
            ? 'rememberMe=; expires=Thu, 01-Jan-1970 00:00:01 UTC; path=/; secure; httponly'
            : 'rememberMe=; expires=Thu, 01-Jan-1970 00:00:01 GMT+0000; path=/; secure; httponly';
        $this->assertEquals($expectsCookie, $result['response']->getHeaderLine('Set-Cookie'));
    }

    /**
     * @return void
     */
    public function testClearIdentityWithCookie(): void
    {
        $identifiers = new IdentifierCollection([
            'RememberMe.RememberMeToken',
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath']
        );
        $response = new Response();

        $identity = new Entity([
            'id' => 1,
            'username' => 'foo',
        ]);
        $identity->setSource('AuthUsers');
        $request = $request
            ->withCookieParams([
                'rememberMe' => CookieAuthenticator::encryptToken('foo', 'series_foo_1', 'logintoken1'),
            ])
            ->withAttribute('identity', $identity);

        $this->assertTrue($this->Tokens->exists(['model' => 'AuthUsers', 'foreign_id' => 1, 'series' => 'series_foo_1']));

        $authenticator = new CookieAuthenticator($identifiers);

        $result = $authenticator->clearIdentity($request, $response);
        $this->assertIsArray($result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);

        // Will deleted login token
        $this->assertFalse($this->Tokens->exists(['model' => 'AuthUsers', 'foreign_id' => 1, 'series' => 'series_foo_1']));
    }
}
