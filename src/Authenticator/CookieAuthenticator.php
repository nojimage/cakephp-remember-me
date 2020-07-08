<?php

namespace RememberMe\Authenticator;

use ArrayAccess;
use Authentication\Authenticator\AbstractAuthenticator;
use Authentication\Authenticator\PersistenceInterface;
use Authentication\Authenticator\Result;
use Authentication\Identifier\IdentifierCollection;
use Authentication\Identifier\IdentifierInterface;
use Authentication\UrlChecker\UrlCheckerTrait;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\RepositoryInterface;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Cookie\CookieInterface;
use Cake\I18n\FrozenTime;
use Cake\ORM\Exception\PersistenceFailedException;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Utility\Hash;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RememberMe\Compat\Security;
use RememberMe\Model\Table\RememberMeTokensTableInterface;

/**
 * Class CookieAuthenticator
 *
 * This authenticator use method of issuing a token, instead of set to cookie encrypted username/password.
 *
 * @mmethod RememberMeTokenIdentifier|IdentifierCollection getIdentifier()
 */
class CookieAuthenticator extends AbstractAuthenticator implements PersistenceInterface
{
    use EncryptCookieTrait;
    use LocatorAwareTrait;
    use UrlCheckerTrait;

    /**
     * {@inheritDoc}
     */
    protected $_defaultConfig = [
        'loginUrl' => null,
        'urlChecker' => 'Authentication.Default',
        'rememberMeField' => 'remember_me',
        'fields' => [
            IdentifierInterface::CREDENTIAL_USERNAME => 'username',
        ],
        'cookie' => [
            'name' => 'rememberMe',
            'expire' => '+30 days',
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httpOnly' => true,
        ],
        'tokenStorageModel' => 'RememberMe.RememberMeTokens',
        'always' => false,
        'dropExpiredToken' => true,
    ];

    /**
     * the constructor.
     *
     * @param IdentifierInterface $identifier Identifier or identifiers collection.
     * @param array $config Configuration settings.
     */
    public function __construct(IdentifierInterface $identifier, array $config = [])
    {
        if (Hash::check($config, 'cookie.expires')) {
            $config['cookie']['expire'] = $config['cookie']['expires'];
            unset($config['cookie']['expires']);
        }
        parent::__construct($identifier, $config);
    }

    /**
     * {@inheritDoc}
     */
    public function authenticate(ServerRequestInterface $request, ResponseInterface $response)
    {
        $cookie = $this->_getCookie($request);

        if ($cookie === null) {
            return new Result(null, Result::FAILURE_CREDENTIALS_MISSING, [
                'Login credentials not found',
            ]);
        }

        try {
            $credentials = static::decodeCookie($cookie);
        } catch (InvalidArgumentException $e) {
            return new Result(null, Result::FAILURE_CREDENTIALS_INVALID, [
                'Cookie token is invalid',
                $e->getMessage(),
            ]);
        }

        if (!isset($credentials['username'], $credentials['series'], $credentials['token'])) {
            return new Result(null, Result::FAILURE_CREDENTIALS_INVALID, [
                'Cookie token is invalid',
            ]);
        }

        $identity = $this->_identifier->identify($credentials);

        if (empty($identity)) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND, $this->_identifier->getErrors());
        }

        return new Result($identity, Result::SUCCESS);
    }

    /**
     * get login token form cookie
     *
     * @param ServerRequestInterface $request a Request instance
     * @return string|null
     */
    protected function _getCookie(ServerRequestInterface $request)
    {
        $cookies = $request->getCookieParams();
        $cookieName = $this->getConfig('cookie.name');

        return isset($cookies[$cookieName]) ? $cookies[$cookieName] : null;
    }

    /**
     * {@inheritDoc}
     */
    public function persistIdentity(ServerRequestInterface $request, ResponseInterface $response, $identity)
    {
        $field = $this->getConfig('rememberMeField');
        $bodyData = $request->getParsedBody();

        if (!$this->_checkUrl($request)
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
        $token = $this->_saveToken($identity, $this->_generateToken($identity));
        $encryptedToken = static::encryptToken(
            $identity[$this->getConfig('fields.' . IdentifierInterface::CREDENTIAL_USERNAME)],
            $token['series'],
            $token['token']
        );
        $cookie = $this->_createCookie($encryptedToken, $token['expires']);

        return [
            'request' => $request,
            'response' => $response->withAddedHeader('Set-Cookie', $cookie->toHeaderValue()),
        ];
    }

    /**
     * generate token
     *
     * @param ArrayAccess|array $identity logged in user info
     * @return string
     */
    protected function _generateToken($identity)
    {
        $prefix = bin2hex(Security::randomBytes(16));

        return Security::hash($prefix . serialize($identity));
    }

    /**
     * save login token to tokens table
     *
     * @param ArrayAccess|array $identity logged in user info
     * @param string $token login token
     * @return EntityInterface
     * @throws PersistenceFailedException
     */
    protected function _saveToken($identity, $token)
    {
        $userModel = null;
        if ($identity instanceof EntityInterface) {
            $userModel = $identity->getSource();
        } elseif ($identifier = $this->_getSuccessfulIdentifier()) {
            $userModel = $identifier->getResolver()->getConfig('userModel');
        }
        if ($userModel === null) {
            throw new InvalidArgumentException('Can\'t detect user model');
        }

        $userTable = $this->getTableLocator()->get($userModel);
        $tokenTable = $this->_getTokenStorageModel();

        if ($this->getConfig('dropExpiredToken')) {
            // drop expired token
            $tokenTable->dropExpired($userModel);
        }

        // create token entity
        $entity = $tokenTable->newEntity([
            'model' => $userModel,
            'foreign_id' => $identity[$userTable->getPrimaryKey()],
            'series' => $this->_generateToken($identity),
            'token' => $token,
            'expires' => new FrozenTime($this->getConfig('cookie.expire')),
        ]);

        return $tokenTable->saveOrFail($entity);
    }

    /**
     * @return IdentifierInterface
     */
    protected function _getSuccessfulIdentifier()
    {
        $identifier = $this->getIdentifier();
        if ($identifier instanceof IdentifierCollection) {
            $identifier = method_exists($identifier, 'getIdentificationProvider')
                ? $identifier->getIdentificationProvider()
                : null;
        }

        return $identifier;
    }

    /**
     * @return RepositoryInterface|RememberMeTokensTableInterface
     */
    protected function _getTokenStorageModel()
    {
        return $this->getTableLocator()->get($this->getConfig('tokenStorageModel'));
    }

    /**
     * {@inheritDoc}
     */
    public function clearIdentity(ServerRequestInterface $request, ResponseInterface $response)
    {
        $cookie = $this->_createCookie(null)->withExpired();

        return [
            'request' => $request,
            'response' => $response->withAddedHeader('Set-Cookie', $cookie->toHeaderValue()),
        ];
    }

    /**
     * Creates a cookie instance with configured defaults.
     *
     * @param mixed $value Cookie value.
     * @param FrozenTime|null $expires the Cookie expire
     * @return CookieInterface
     */
    protected function _createCookie($value, FrozenTime $expires = null)
    {
        $data = $this->getConfig('cookie');

        return new Cookie(
            $data['name'],
            $value,
            $expires,
            $data['path'],
            $data['domain'],
            $data['secure'],
            $data['httpOnly']
        );
    }
}
