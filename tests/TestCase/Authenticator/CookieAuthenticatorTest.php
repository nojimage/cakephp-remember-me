<?php
declare(strict_types=1);

namespace RememberMe\Test\TestCase\Authenticator;

use Authentication\Authenticator\Result;
use Authentication\Authenticator\ResultInterface;
use Authentication\Identifier\IdentifierCollection;
use Authentication\Identifier\PasswordIdentifier;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Http\Cookie\CookieInterface;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\I18n\DateTime;
use Cake\ORM\Entity;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RememberMe\Authenticator\CookieAuthenticator;
use RememberMe\Identifier\RememberMeTokenIdentifier;
use RememberMe\Model\Entity\RememberMeToken;
use RememberMe\Model\Table\RememberMeTokensTable;
use RememberMe\Test\TestCase\RememberMeTestCase as TestCase;

class CookieAuthenticatorTest extends TestCase
{
    /**
     * @var RememberMeTokensTable
     */
    private RememberMeTokensTable $Tokens;

    public function setUp(): void
    {
        parent::setUp();
        /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
        $this->Tokens = $this->fetchTable('RememberMe.RememberMeTokens');
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
        DateTime::setTestNow();
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testAuthenticateCredentialsNotPresent(): void
    {
        $identifier = new RememberMeTokenIdentifier([
            'resolver' => [
                'className' => 'Authentication.Orm',
                'userModel' => 'AuthUsers',
            ],
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
        );

        $authenticator = new CookieAuthenticator($identifier);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(ResultInterface::FAILURE_CREDENTIALS_MISSING, $result->getStatus());
    }

    /**
     * @return void
     */
    public function testAuthenticateEmptyCookie(): void
    {
        $identifier = new RememberMeTokenIdentifier([
            'resolver' => [
                'className' => 'Authentication.Orm',
                'userModel' => 'AuthUsers',
            ],
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'rememberMe' => '',
            ],
        );

        $authenticator = new CookieAuthenticator($identifier);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(ResultInterface::FAILURE_CREDENTIALS_MISSING, $result->getStatus());
    }

    /**
     * @return void
     */
    public function testAuthenticateUnencryptedCookie(): void
    {
        $identifier = new RememberMeTokenIdentifier([
            'resolver' => [
                'className' => 'Authentication.Orm',
                'userModel' => 'AuthUsers',
            ],
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'rememberMe' => 'unencrypted',
            ],
        );

        $authenticator = new CookieAuthenticator($identifier);
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
        $identifier = new RememberMeTokenIdentifier([
            'resolver' => [
                'className' => 'Authentication.Orm',
                'userModel' => 'AuthUsers',
            ],
        ]);

        $encryptedToken = CookieAuthenticator::encryptToken('foo', 'series_foo_1', 'logintoken2');
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'rememberMe' => $encryptedToken,
            ],
        );

        $authenticator = new CookieAuthenticator($identifier);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(ResultInterface::FAILURE_IDENTITY_NOT_FOUND, $result->getStatus());
        $this->assertSame(['token does not match'], $result->getErrors());
    }

    /**
     * @return void
     */
    public function testAuthenticateExpired(): void
    {
        DateTime::setTestNow('2017-10-01 11:22:34');
        $identifier = new RememberMeTokenIdentifier([
            'resolver' => [
                'className' => 'Authentication.Orm',
                'userModel' => 'AuthUsers',
            ],
        ]);

        $encryptedToken = CookieAuthenticator::encryptToken('foo', 'series_foo_1', 'logintoken1');
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'rememberMe' => $encryptedToken,
            ],
        );

        $authenticator = new CookieAuthenticator($identifier);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(ResultInterface::FAILURE_IDENTITY_NOT_FOUND, $result->getStatus());
        $this->assertSame(['token expired'], $result->getErrors());
    }

    /**
     * @return void
     */
    public function testAuthenticateValid(): void
    {
        DateTime::setTestNow('2017-10-01 11:22:33');
        $identifier = new RememberMeTokenIdentifier([
            'resolver' => [
                'className' => 'Authentication.Orm',
                'userModel' => 'AuthUsers',
            ],
        ]);

        $encryptedToken = CookieAuthenticator::encryptToken('foo', 'series_foo_1', 'logintoken1');
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'rememberMe' => $encryptedToken,
            ],
        );

        $authenticator = new CookieAuthenticator($identifier);
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
        $identifier = new PasswordIdentifier();

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
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

        $authenticator = new CookieAuthenticator($identifier);

        $result = $authenticator->persistIdentity($request, $response, $identity);

        $this->assertIsArray($result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);

        $token = $this->Tokens->find()->orderByDesc('id')->first();
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
        $authenticator = new CookieAuthenticator($identifier, [
            'rememberMeField' => 'other_field',
        ]);
        $result = $authenticator->persistIdentity($request, $response, $identity);
        $this->assertStringContainsString('rememberMe=', $result['response']->getHeaderLine('Set-Cookie'));
    }

    /**
     * @return void
     */
    public function testConstructWithoutIdentifier(): void
    {
        $authenticator = new CookieAuthenticator(null, [
            'fields' => ['username' => 'email'],
            'tokenStorageModel' => 'MyTokens',
        ]);

        $identifier = $authenticator->getIdentifier();

        $this->assertInstanceOf(RememberMeTokenIdentifier::class, $identifier);
        $this->assertSame('email', $identifier->getConfig('fields.username'));
        $this->assertSame('MyTokens', $identifier->getConfig('tokenStorageModel'));
    }

    /**
     * @return void
     */
    public function testAuthenticateWithoutIdentifier(): void
    {
        $token = $this->Tokens->saveOrFail($this->Tokens->newEntity([
            'model' => 'Users',
            'foreign_id' => 1,
            'series' => 'series_mariano_1',
            'token' => 'logintoken_mariano_1',
            'expires' => new DateTime('+1 day'),
        ]));
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'rememberMe' => CookieAuthenticator::encryptToken('mariano', $token->series, $token->token),
            ],
        );
        $authenticator = new CookieAuthenticator();

        $result = $authenticator->authenticate($request);

        $this->assertSame(ResultInterface::SUCCESS, $result->getStatus());
        $this->assertSame('mariano', $result->getData()['username']);
    }

    /**
     * @return void
     */
    public function testPersistIdentityWithArrayIdentity(): void
    {
        $identifier = new RememberMeTokenIdentifier([
            'resolver' => [
                'className' => 'Authentication.Orm',
                'userModel' => 'AuthUsers',
            ],
        ]);
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
        )->withParsedBody(['remember_me' => 1]);
        $authenticator = new CookieAuthenticator($identifier);

        $authenticator->persistIdentity($request, new Response(), ['id' => 1, 'username' => 'foo']);

        $token = $this->Tokens->find()->orderByDesc('id')->firstOrFail();
        $this->assertSame('AuthUsers', $token->model);
        $this->assertSame('1', $token->foreign_id);
    }

    /**
     * authentication 3.x: the user model comes from the identifier that succeeded in the collection.
     *
     * @return void
     */
    public function testPersistIdentityWithIdentifierCollection(): void
    {
        if (!class_exists(IdentifierCollection::class)) {
            $this->markTestSkipped('IdentifierCollection exists only in cakephp/authentication 3.x');
        }
        $identifiers = new IdentifierCollection([
            'Authentication.Password' => [
                'resolver' => [
                    'className' => 'Authentication.Orm',
                    'userModel' => 'AuthUsers',
                ],
            ],
        ]);
        $identifiers->identify(['username' => 'foo', 'password' => '12345678']);
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
        )->withParsedBody(['remember_me' => 1]);
        $authenticator = new CookieAuthenticator($identifiers);

        $authenticator->persistIdentity($request, new Response(), ['id' => 1, 'username' => 'foo']);

        $token = $this->Tokens->find()->orderByDesc('id')->firstOrFail();
        $this->assertSame('AuthUsers', $token->model);
        $this->assertSame('1', $token->foreign_id);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function loginUrlProvider(): array
    {
        return [
            'login url' => ['/users/login', true],
            'other url' => ['/testpath', false],
        ];
    }

    /**
     * @param string $requestUri request path
     * @param bool $expected whether the cookie is issued
     * @return void
     */
    #[DataProvider('loginUrlProvider')]
    public function testPersistIdentityChecksLoginUrl(string $requestUri, bool $expected): void
    {
        $identity = new Entity(['id' => 1, 'username' => 'foo']);
        $identity->setSource('AuthUsers');
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => $requestUri],
        )->withParsedBody(['remember_me' => 1]);
        $authenticator = new CookieAuthenticator(new RememberMeTokenIdentifier(), [
            'loginUrl' => '/users/login',
        ]);

        $result = $authenticator->persistIdentity($request, new Response(), $identity);

        $this->assertSame($expected, str_contains($result['response']->getHeaderLine('Set-Cookie'), 'rememberMe='));
    }

    /**
     * @return void
     */
    public function testPersistIdentityCanTwice(): void
    {
        $identifier = new PasswordIdentifier();

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
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

        $authenticator = new CookieAuthenticator($identifier);

        $authenticator->persistIdentity($request, $response, $identity);
        $authenticator->persistIdentity($request, $response, $identity);

        $token = $this->Tokens->find()->orderByDesc('id')->first();
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
        $identifier = new PasswordIdentifier();
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
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

        $authenticator = new CookieAuthenticator($identifier);

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
        $identifier = new RememberMeTokenIdentifier();

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
        );
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifier);

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
        $identifier = new RememberMeTokenIdentifier();

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
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

        $authenticator = new CookieAuthenticator($identifier);

        $result = $authenticator->clearIdentity($request, $response);
        $this->assertIsArray($result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);

        // Will deleted login token
        $this->assertFalse($this->Tokens->exists(['model' => 'AuthUsers', 'foreign_id' => 1, 'series' => 'series_foo_1']));
    }

    /**
     * @return void
     */
    public function testClearIdentityWithCompositePrimaryKey(): void
    {
        $identifier = new RememberMeTokenIdentifier();

        $this->fetchTable('AuthUsers')->setPrimaryKey(['id', 'username']);

        $identity = new Entity([
            'id' => 1,
            'username' => 'foo',
        ]);
        $identity->setSource('AuthUsers');
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
        )
            ->withCookieParams([
                'rememberMe' => CookieAuthenticator::encryptToken('foo', 'series_foo_1', 'logintoken1'),
            ])
            ->withAttribute('identity', $identity);

        $authenticator = new CookieAuthenticator($identifier);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User model must have a single primary key.');

        $authenticator->clearIdentity($request, new Response());
    }
}
