<?php
declare(strict_types=1);

namespace RememberMe\Test\TestCase\Authenticator;

use Authentication\AuthenticationService;
use Cake\Datasource\EntityInterface;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Http\ServerRequestFactory;
use Cake\I18n\DateTime;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use RememberMe\Authenticator\CookieAuthenticator;
use RememberMe\Model\Table\RememberMeTokensTable;
use RememberMe\Test\TestCase\RememberMeTestCase as TestCase;

/**
 * Loads CookieAuthenticator through AuthenticationService with each supported configuration style.
 */
class AuthenticationServiceIntegrationTest extends TestCase
{
    private RememberMeTokensTable $Tokens;

    public function setUp(): void
    {
        parent::setUp();
        /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
        $this->Tokens = $this->fetchTable('RememberMe.RememberMeTokens');
    }

    /**
     * @return bool
     */
    private static function isAuthentication3(): bool
    {
        return method_exists(AuthenticationService::class, 'loadIdentifier');
    }

    /**
     * Each case: [service builder, required major version or null, user model, username].
     *
     * @return array<string, array{\Closure, int|null, string, string}>
     */
    public static function serviceProvider(): array
    {
        $authUsersOptions = [
            'fields' => ['username' => 'username'],
            'resolver' => [
                'className' => 'Authentication.Orm',
                'userModel' => 'AuthUsers',
            ],
        ];

        return [
            'identifier name' => [
                function (): AuthenticationService {
                    $service = new AuthenticationService();
                    $service->loadAuthenticator('RememberMe.Cookie', [
                        'identifier' => 'RememberMe.RememberMeToken',
                    ]);

                    return $service;
                },
                null,
                'Users',
                'mariano',
            ],
            'identifier with options' => [
                function () use ($authUsersOptions): AuthenticationService {
                    // 3.x builds an IdentifierCollection from the array, so the key is the identifier name.
                    $identifier = self::isAuthentication3()
                        ? ['RememberMe.RememberMeToken' => $authUsersOptions]
                        : ['className' => 'RememberMe.RememberMeToken'] + $authUsersOptions;
                    $service = new AuthenticationService();
                    $service->loadAuthenticator('RememberMe.Cookie', [
                        'identifier' => $identifier,
                    ]);

                    return $service;
                },
                null,
                'AuthUsers',
                'foo',
            ],
            'loadIdentifier' => [
                function () use ($authUsersOptions): AuthenticationService {
                    $service = new AuthenticationService();
                    // loadIdentifier() is deprecated since 3.3.0. Whether the warning is emitted depends on
                    // error_reporting under the test runner, so mute it instead of asserting it.
                    $errorLevel = error_reporting(E_ALL & ~E_USER_DEPRECATED);
                    try {
                        $service->loadIdentifier('RememberMe.RememberMeToken', $authUsersOptions);
                        $service->loadAuthenticator('RememberMe.Cookie');
                    } finally {
                        error_reporting($errorLevel);
                    }

                    return $service;
                },
                3,
                'AuthUsers',
                'foo',
            ],
            'without identifier' => [
                function (): AuthenticationService {
                    $service = new AuthenticationService();
                    $service->loadAuthenticator('RememberMe.Cookie');

                    return $service;
                },
                4,
                'Users',
                'mariano',
            ],
        ];
    }

    /**
     * @param int|null $major required major version of cakephp/authentication
     * @return void
     */
    private function skipUnlessSupported(?int $major): void
    {
        if ($major === 3 && !self::isAuthentication3()) {
            $this->markTestSkipped('This configuration is only for cakephp/authentication 3.x');
        }
        if ($major === 4 && self::isAuthentication3()) {
            $this->markTestSkipped('This configuration is only for cakephp/authentication 4.x');
        }
    }

    /**
     * @param string $userModel user model name
     * @return \Cake\Datasource\EntityInterface token
     */
    private function saveToken(string $userModel): EntityInterface
    {
        return $this->Tokens->saveOrFail($this->Tokens->newEntity([
            'model' => $userModel,
            'foreign_id' => 1,
            'series' => 'series_integration',
            'token' => 'logintoken_integration',
            'expires' => new DateTime('+1 day'),
        ]));
    }

    /**
     * @param string $username login username
     * @param \Cake\Datasource\EntityInterface $token token
     * @return \Cake\Http\ServerRequest
     */
    private function requestWithCookie(string $username, EntityInterface $token): ServerRequest
    {
        return ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'rememberMe' => CookieAuthenticator::encryptToken($username, $token['series'], $token['token']),
            ],
        );
    }

    #[DataProvider('serviceProvider')]
    public function testAuthenticate(Closure $builder, ?int $major, string $userModel, string $username): void
    {
        $this->skipUnlessSupported($major);
        $request = $this->requestWithCookie($username, $this->saveToken($userModel));
        $service = $builder();

        $result = $service->authenticate($request);

        $this->assertTrue($result->isValid(), implode(', ', $result->getErrors()));
        $this->assertSame($username, $result->getData()['username']);
    }

    #[DataProvider('serviceProvider')]
    public function testPersistIdentity(Closure $builder, ?int $major, string $userModel, string $username): void
    {
        $this->skipUnlessSupported($major);
        $identity = $this->fetchTable($userModel)->get(1);
        $request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/testpath'])
            ->withParsedBody(['remember_me' => 1]);
        $service = $builder();

        $result = $service->persistIdentity($request, new Response(), $identity);

        $this->assertStringContainsString('rememberMe=', $result['response']->getHeaderLine('Set-Cookie'));
        $token = $this->Tokens->find()->orderByDesc('id')->firstOrFail();
        $this->assertSame($userModel, $token->model);
        $this->assertSame('1', $token->foreign_id);
    }

    #[DataProvider('serviceProvider')]
    public function testClearIdentity(Closure $builder, ?int $major, string $userModel, string $username): void
    {
        $this->skipUnlessSupported($major);
        $token = $this->saveToken($userModel);
        $request = $this->requestWithCookie($username, $token)
            ->withAttribute('identity', $this->fetchTable($userModel)->get(1));
        $service = $builder();

        $service->clearIdentity($request, new Response());

        $this->assertFalse($this->Tokens->exists(['id' => $token['id']]));
    }
}
