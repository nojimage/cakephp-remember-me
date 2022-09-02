<?php
declare(strict_types=1);

namespace RememberMe\Auth;

use Cake\Auth\BaseAuthenticate;
use Cake\Controller\ComponentRegistry;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\ModelAwareTrait;
use Cake\Datasource\RepositoryInterface;
use Cake\Event\Event;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\I18n\FrozenTime;
use Cake\ORM\Query;
use Cake\Utility\Hash;
use InvalidArgumentException;
use RememberMe\Authenticator\EncryptCookieTrait;
use RememberMe\Model\Entity\RememberMeToken;

/**
 * Cookie Authenticate
 *
 * @deprecated 4.0.0 Use the cakephp/authentication and CookieAuthenticator instead.
 */
class CookieAuthenticate extends BaseAuthenticate
{
    use EncryptCookieTrait;
    use ModelAwareTrait;

    public static $userTokenFieldName = 'remember_me_token';

    /**
     * Constructor
     *
     * @param \Cake\Controller\ComponentRegistry $registry components
     * @param array $config authenticate config
     */
    public function __construct(ComponentRegistry $registry, array $config = [])
    {
        $this->setConfig([
            'fields' => [
                'username' => 'username',
            ],
            'inputKey' => 'remember_me',
            'always' => false,
            'dropExpiredToken' => true,
            'cookie' => [
                'name' => 'rememberMe',
                'expires' => '+30 days',
                'secure' => true,
                'httpOnly' => true,
            ],
            'tokenStorageModel' => 'RememberMe.RememberMeTokens',
            'userModel' => 'Users',
            'scope' => [],
            'contain' => null,
        ]);
        parent::__construct($registry, $config);
    }

    /**
     * authenticate
     *
     * @param \Cake\Http\ServerRequest $request a Request instance
     * @param \Cake\Http\Response $response a Response instance
     * @return array|false
     */
    public function authenticate(ServerRequest $request, Response $response)
    {
        return $this->getUser($request);
    }

    /**
     * get user from cookie
     *
     * @param \Cake\Http\ServerRequest $request a Request instance.
     * @return array|bool
     */
    public function getUser(ServerRequest $request)
    {
        if (!$this->checkFields($request)) {
            return false;
        }

        $credentials = static::decodeCookie($this->getCookie($request));

        $user = $this->findUserAndTokenBySeries($credentials['username'], $credentials['series']);

        if ($user === null) {
            return false;
        }

        if (!$this->verifyToken($user, $credentials['token'])) {
            $this->dropInvalidToken($user);

            return false;
        }

        // remove password field
        return Hash::remove($user->toArray(), $this->getConfig('fields.password'));
    }

    /**
     * Checks the fields to ensure they are supplied.
     *
     * @param \Cake\Http\ServerRequest $request The request that contains login information.
     * @return bool False if the fields have not been supplied. True if they exist.
     */
    protected function checkFields(ServerRequest $request): bool
    {
        $cookie = $this->getCookie($request);
        if (empty($cookie)) {
            return false;
        }

        try {
            $credentials = self::decodeCookie($cookie);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        return isset($credentials['username'], $credentials['series'], $credentials['token']);
    }

    /**
     * get login token form cookie
     *
     * @param \Cake\Http\ServerRequest $request a Request instance
     * @return string
     */
    protected function getCookie(ServerRequest $request): string
    {
        $cookie = $request->getCookie($this->getConfig('cookie.name'), '');

        return $cookie ?? '';
    }

    /**
     * set login token to cookie
     *
     * @param \Cake\Http\Response $response a Response instance.
     * @param string $cookie encrypted login token
     * @return \Cake\Http\Response
     */
    protected function setCookie(Response $response, string $cookie): Response
    {
        $config = $this->getConfig('cookie');
        $expires = new FrozenTime($config['expires']);
        $cookieObj = new Cookie(
            $this->getConfig('cookie.name'),
            $cookie,
            $expires,
            $this->getConfig('cookie.path', '/'),
            $this->getConfig('cookie.domain', ''),
            $this->getConfig('cookie.secure', true),
            $this->getConfig('cookie.httpOnly', true)
        );

        return $response->withCookie($cookieObj);
    }

    /**
     * save login token to tokens table
     *
     * @param array $user logged in user info
     * @param string $token login token
     * @return \Cake\Datasource\EntityInterface|false
     */
    protected function saveToken(array $user, string $token)
    {
        $userModel = $this->getConfig('userModel');
        $userTable = $this->getUsersTable();
        $tokenTable = $this->getTokensTable();

        $entity = null;
        $id = Hash::get($user, static::$userTokenFieldName . '.id');
        $expires = new FrozenTime($this->getConfig('cookie.expires'));

        if ($id) {
            // update token
            $entity = $tokenTable->get($id);
            $tokenTable->patchEntity($entity, [
                'token' => $token,
                'expires' => $expires,
            ]);
        } else {
            // new token
            $entity = $tokenTable->newEntity([
                'model' => $userModel,
                'foreign_id' => $user[$userTable->getPrimaryKey()],
                'series' => static::_generateToken($user),
                'token' => $token,
                'expires' => $expires,
            ]);
        }

        return $tokenTable->save($entity);
    }

    /**
     * associate with RememberMeTokens to Users table
     *
     * @return void
     */
    protected function initializeUserModel(): void
    {
        $userModel = $this->getConfig('userModel');

        $table = $this->getUsersTable();

        if (!$table->associations()->has('RememberMeTokens')) {
            $table->hasMany('RememberMeTokens', [
                'className' => $this->getConfig('tokenStorageModel'),
                'foreignKey' => 'foreign_id',
                'conditions' => ['RememberMeTokens.model' => $userModel],
                'dependent' => true,
            ]);
        }
    }

    /**
     * find user with username and series
     *
     * @param string $username request username
     * @param string $series request series
     * @return \Cake\Datasource\EntityInterface|null
     */
    protected function findUserAndTokenBySeries(string $username, string $series): ?EntityInterface
    {
        $this->initializeUserModel();

        $query = $this->_query($username);

        if (!empty($query->clause('select'))) {
            $query->select($this->getTokensTable());
        }

        $query->matching('RememberMeTokens', function (Query $q) use ($series) {
            return $q->where(['RememberMeTokens.series' => $series]);
        });

        $user = $query->first();

        if (!$user) {
            return null;
        }

        // change mapping
        $matchingData = $user->get('_matchingData');
        $user->set(static::$userTokenFieldName, $matchingData['RememberMeTokens']);
        $user->unset('_matchingData');

        return $user;
    }

    /**
     * verify user token, match and expires
     *
     * @param \Cake\Datasource\EntityInterface $user logged in user info
     * @param string $verifyToken token from cookie
     * @return bool
     */
    protected function verifyToken(EntityInterface $user, string $verifyToken): bool
    {
        $token = $this->getTokenFromUserEntity($user);

        if ($token->token !== $verifyToken) {
            return false;
        }

        if (FrozenTime::now()->gt($token->expires)) {
            return false;
        }

        return true;
    }

    /**
     * drop invalid token
     *
     * @param \Cake\Datasource\EntityInterface $user logged in user info
     * @return bool
     */
    protected function dropInvalidToken(EntityInterface $user): bool
    {
        $token = $this->getTokenFromUserEntity($user);

        return $this->getTokensTable()->delete($token);
    }

    /**
     * get token
     *
     * @param \Cake\Datasource\EntityInterface $user logged in user info
     * @return \RememberMe\Model\Entity\RememberMeToken
     * @throws \InvalidArgumentException
     */
    protected function getTokenFromUserEntity(EntityInterface $user): RememberMeToken
    {
        if (empty($user->{static::$userTokenFieldName})) {
            throw new InvalidArgumentException('user entity has not matching token data.');
        }

        return $user->{static::$userTokenFieldName};
    }

    // =====
    // Event Hooks
    // =====

    /**
     * @return array
     */
    public function implementedEvents(): array
    {
        return [
            'Auth.afterIdentify' => 'onAfterIdentify',
            'Auth.logout' => 'onLogout',
        ];
    }

    /**
     * event on 'Auth.afterIdentify'
     *
     * @param \Cake\Event\Event $event a Event instance
     * @param array $user logged in user info
     * @return array
     */
    public function onAfterIdentify(Event $event, array $user): array
    {
        /** @var \Cake\Controller\Component\AuthComponent $authComponent */
        $authComponent = $event->getSubject();
        $controller = $authComponent->getController();
        $request = $controller->getRequest();
        $response = $controller->getResponse();

        if (!$user) {
            // when authenticate failed, clear cookie token.
            $controller->setResponse($this->setCookie($response, ''));

            return [];
        }

        if ($this->getConfig('dropExpiredToken')) {
            // drop expired token
            $this->getTokensTable()->dropExpired($this->getConfig('userModel'));
        }

        if ($this->getConfig('always') || $request->getData($this->getConfig('inputKey'))) {
            // -- set token to cookie & session
            // save token
            $token = $this->saveToken($user, static::_generateToken($user));

            if ($token) {
                // write cookie
                $controller->setResponse($this->setLoginTokenToCookie($response, $user, $token));
                // set token to user
                $user[static::$userTokenFieldName] = $token->toArray();

                return $user;
            }
        }

        return [];
    }

    /**
     * Generate and set login token to Response
     *
     * @param \Cake\Http\Response $response a Response instance
     * @param array $user logged in user info
     * @param \RememberMe\Model\Entity\RememberMeToken|\Cake\Datasource\EntityInterface $token a Token instance
     * @return \Cake\Http\Response
     */
    protected function setLoginTokenToCookie(Response $response, array $user, EntityInterface $token): Response
    {
        if (isset($user[$this->getConfig('fields.username')])) {
            // write cookie
            $username = $user[$this->getConfig('fields.username')];
            $cookieToken = static::encryptToken($username, $token->series, $token->token);
            $response = $this->setCookie($response, $cookieToken);
        }

        return $response;
    }

    /**
     * event on 'Auth.logout'
     *
     * @param \Cake\Event\Event $event a Event instance
     * @param array $user logged in user info
     * @return bool
     */
    public function onLogout(Event $event, array $user): bool
    {
        /** @var \Cake\Controller\Component\AuthComponent $authComponent */
        $authComponent = $event->getSubject();
        $controller = $authComponent->getController();
        $controller->setResponse($this->setCookie($controller->getResponse(), ''));

        // drop token
        $this->dropToken($user);

        return true;
    }

    /**
     * drop token for logout event
     *
     * @param array $user logged in user info
     * @return bool
     */
    protected function dropToken(array $user): bool
    {
        $token = $this->getTokenFromUserArray($user);

        if (!$token) {
            return false;
        }

        return $this->getTokensTable()->delete($token);
    }

    /**
     * Get token entity from user data array
     *
     * @param array $user logged in user info
     * @return \RememberMe\Model\Entity\RememberMeToken|null
     */
    protected function getTokenFromUserArray(array $user): ?RememberMeToken
    {
        if (empty($user[static::$userTokenFieldName])) {
            return null;
        }

        $tokenTable = $this->getTokensTable();

        return $tokenTable->find()
            ->where([
                'id' => $user[static::$userTokenFieldName]['id'],
            ])
            ->first();
    }

    /**
     * @return \Cake\ORM\Table
     */
    protected function getUsersTable(): RepositoryInterface
    {
        return $this->loadModel($this->getConfig('userModel'));
    }

    /**
     * @return \RememberMe\Model\Table\RememberMeTokensTableInterface
     */
    protected function getTokensTable(): RepositoryInterface
    {
        return $this->loadModel($this->getConfig('tokenStorageModel'));
    }
}
