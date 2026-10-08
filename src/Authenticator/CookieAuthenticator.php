<?php
declare(strict_types=1);

namespace RememberMe\Authenticator;

use ArrayAccess;
use Authentication\Authenticator\AbstractAuthenticator;
use Authentication\Authenticator\PersistenceInterface;
use Authentication\Authenticator\Result;
use Authentication\Authenticator\ResultInterface;
use Authentication\Identifier\IdentifierInterface;
use Authentication\UrlChecker\UrlCheckerTrait;
use Cake\Datasource\EntityInterface;
use Cake\Http\Cookie\Cookie;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Utility\Hash;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RememberMe\Identifier\RememberMeTokenIdentifier;

/**
 * Class CookieAuthenticator
 *
 * This authenticator use method of issuing a token, instead of set to cookie encrypted username/password.
 */
class CookieAuthenticator extends AbstractAuthenticator implements PersistenceInterface
{
    use EncryptCookieTrait;
    use LocatorAwareTrait;
    use UrlCheckerTrait;

    /**
     * @inheritDoc
     */
    protected array $_defaultConfig = [
        'loginUrl' => null,
        'urlChecker' => 'Authentication.Default',
        'rememberMeField' => 'remember_me',
        'fields' => [
            RememberMeTokenIdentifier::CREDENTIAL_USERNAME => 'username',
        ],
        'cookie' => [
            'name' => 'rememberMe',
            'expire' => '+30 days',
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httpOnly' => true,
        ],
        'identityAttribute' => 'identity',
        'tokenStorageModel' => 'RememberMe.RememberMeTokens',
        'always' => false,
        'dropExpiredToken' => true,
    ];

    /**
     * the constructor.
     *
     * @param \Authentication\Identifier\IdentifierInterface|null $identifier Identifier or identifiers collection.
     *   When null, a RememberMeTokenIdentifier is created with `fields` and `tokenStorageModel` from $config.
     * @param array $config Configuration settings.
     */
    public function __construct(?IdentifierInterface $identifier = null, array $config = [])
    {
        if (Hash::check($config, 'cookie.expires')) {
            $config['cookie']['expire'] = $config['cookie']['expires'];
            unset($config['cookie']['expires']);
        }
        // authentication 3.x rejects a null identifier in the parent constructor, so build the default here.
        $identifier ??= new RememberMeTokenIdentifier(
            array_intersect_key($config, array_flip(['fields', 'tokenStorageModel'])),
        );
        parent::__construct($identifier, $config);
    }

    /**
     * @inheritDoc
     */
    public function authenticate(ServerRequestInterface $request): ResultInterface
    {
        $cookie = $this->_getCookie($request);

        if ($cookie === '') {
            return new Result(null, ResultInterface::FAILURE_CREDENTIALS_MISSING, [
                'Login credentials not found',
            ]);
        }

        try {
            $credentials = static::decodeCookie($cookie);
        } catch (InvalidArgumentException $e) {
            return new Result(null, ResultInterface::FAILURE_CREDENTIALS_INVALID, [
                'Cookie token is invalid',
                $e->getMessage(),
            ]);
        }

        if (!isset($credentials['username'], $credentials['series'], $credentials['token'])) {
            return new Result(null, ResultInterface::FAILURE_CREDENTIALS_INVALID, [
                'Cookie token is invalid',
            ]);
        }

        $identifier = $this->getIdentifier();
        $identity = $identifier->identify($credentials);

        if (empty($identity)) {
            return new Result(null, ResultInterface::FAILURE_IDENTITY_NOT_FOUND, $identifier->getErrors());
        }

        return new Result($identity, ResultInterface::SUCCESS);
    }

    /**
     * get login token form cookie
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request a Request instance
     * @return string
     */
    protected function _getCookie(ServerRequestInterface $request): string
    {
        $cookies = $request->getCookieParams();
        $cookieName = $this->getConfig('cookie.name');

        return $cookies[$cookieName] ?? '';
    }

    /**
     * @inheritDoc
     */
    public function persistIdentity(ServerRequestInterface $request, ResponseInterface $response, $identity): array
    {
        $field = $this->getConfig('rememberMeField');
        $bodyData = $request->getParsedBody();

        if (
            !$this->_checkUrl($request)
            || (
                !$this->getConfig('always')
                && (!is_array($bodyData) || empty($bodyData[$field]))
            )
        ) {
            return [
                'request' => $request,
                'response' => $response,
            ];
        }
        $token = $this->_saveToken($identity, static::_generateToken($identity));
        $encryptedToken = static::encryptToken(
            $identity[$this->getConfig('fields.' . RememberMeTokenIdentifier::CREDENTIAL_USERNAME)],
            $token['series'],
            $token['token'],
        );
        $cookie = $this->_createCookie($encryptedToken, $token['expires']);

        return [
            'request' => $request,
            'response' => $response->withAddedHeader('Set-Cookie', $cookie->toHeaderValue()),
        ];
    }

    /**
     * save login token to tokens table
     *
     * @param \ArrayAccess|array $identity logged in user info
     * @param string $token login token
     * @return \Cake\Datasource\EntityInterface
     * @throws \Cake\ORM\Exception\PersistenceFailedException
     */
    protected function _saveToken(ArrayAccess|array $identity, string $token): EntityInterface
    {
        $userModel = $this->_getUserModel($identity);
        if ($userModel === null) {
            throw new InvalidArgumentException('Can\'t detect user model');
        }

        $usersTable = $this->fetchTable($userModel);
        $primaryKey = $usersTable->getPrimaryKey();
        if (!is_string($primaryKey)) {
            throw new InvalidArgumentException('User model must have a single primary key.');
        }

        /** @var \RememberMe\Model\Table\RememberMeTokensTableInterface&\Cake\ORM\Table $tokenTable */
        $tokenTable = $this->fetchTable($this->getConfig('tokenStorageModel'));

        if ($this->getConfig('dropExpiredToken')) {
            // drop expired token
            $tokenTable->dropExpired($userModel);
        }

        // create token entity
        $entity = $tokenTable->newEntity([
            'model' => $userModel,
            'foreign_id' => $identity[$primaryKey],
            'series' => static::_generateToken($identity),
            'token' => $token,
            'expires' => new DateTime($this->getConfig('cookie.expire')),
        ]);

        return $tokenTable->saveOrFail($entity);
    }

    /**
     * @return \Authentication\Identifier\IdentifierInterface|null
     */
    protected function _getSuccessfulIdentifier(): ?IdentifierInterface
    {
        $identifier = $this->getIdentifier();
        // IdentifierCollection (authentication 3.x only): use the identifier that succeeded.
        if (method_exists($identifier, 'getIdentificationProvider')) {
            $identifier = $identifier->getIdentificationProvider();
        }

        return $identifier instanceof IdentifierInterface ? $identifier : null;
    }

    /**
     * @inheritDoc
     */
    public function clearIdentity(ServerRequestInterface $request, ResponseInterface $response): array
    {
        // drop token
        $cookie = $this->_getCookie($request);
        try {
            $credentials = static::decodeCookie($cookie);
        } catch (InvalidArgumentException $e) {
            // nothing to do
        }
        $identity = $request->getAttribute($this->getConfig('identityAttribute'));
        if (isset($credentials['series']) && $identity instanceof EntityInterface && !empty($identity->getSource())) {
            $userModel = $identity->getSource();
            $primaryKey = $this->fetchTable($userModel)->getPrimaryKey();
            if (!is_string($primaryKey)) {
                throw new InvalidArgumentException('User model must have a single primary key.');
            }
            $tokenTable = $this->fetchTable($this->getConfig('tokenStorageModel'));
            $conditions = [
                'model' => $userModel,
                'foreign_id' => $identity[$primaryKey],
                'series' => $credentials['series'],
            ];
            $tokenTable->deleteAll($conditions);
        }

        // clear cookie
        $cookie = $this->_createCookie('')->withExpired();

        return [
            'request' => $request,
            'response' => $response->withAddedHeader('Set-Cookie', $cookie->toHeaderValue()),
        ];
    }

    /**
     * Creates a cookie instance with configured defaults.
     *
     * @param string $value Cookie value.
     * @param \Cake\I18n\DateTime|null $expires the Cookie expire
     * @return \Cake\Http\Cookie\Cookie
     */
    protected function _createCookie(string $value, ?DateTime $expires = null): Cookie
    {
        $data = $this->getConfig('cookie');

        return new Cookie(
            $data['name'],
            $value,
            $expires,
            $data['path'],
            $data['domain'],
            $data['secure'],
            $data['httpOnly'],
        );
    }

    /**
     * @param \ArrayAccess|array $identity logged in user info
     * @return string|null
     */
    protected function _getUserModel(ArrayAccess|array $identity): ?string
    {
        if ($identity instanceof EntityInterface) {
            return $identity->getSource();
        }

        $identifier = $this->_getSuccessfulIdentifier();
        if ($identifier && method_exists($identifier, 'getResolver')) {
            return $identifier->getResolver()->getConfig('userModel');
        }

        return null;
    }
}
