<?php

namespace RememberMe\Auth;

use Cake\Auth\BaseAuthenticate;
use Cake\Controller\ComponentRegistry;
use Cake\Controller\Component\AuthComponent;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\I18n\FrozenTime;
use Cake\ORM\Query;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use InvalidArgumentException;
use RememberMe\Compat\Security;
use RememberMe\Model\Entity\RememberMeToken;
use RememberMe\Model\Table\RememberMeTokensTable;

/**
 * Cookie Authenticate
 */
class CookieAuthenticate extends BaseAuthenticate
{

    public static $userTokenFieldName = 'remember_me_token';

    /**
     * Constructor
     *
     * @param ComponentRegistry $registry components
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
     * @param ServerRequest $request a Request instance
     * @param Response $response a Response instance
     * @return array
     */
    public function authenticate(ServerRequest $request, Response $response)
    {
        return $this->getUser($request);
    }

    /**
     * get user from cookie
     *
     * @param Request $request a Request instance.
     * @return bool
     */
    public function getUser(ServerRequest $request)
    {
        if (!$this->checkFields($request)) {
            return false;
        }
        $cookieParams = $this->decodeCookie($this->getCookie($request));

        $user = $this->findUserAndTokenBySeries($cookieParams['username'], $cookieParams['series']);

        if (empty($user)) {
            return false;
        }

        if (!$this->verifyToken($user, $cookieParams['token'])) {
            $this->dropInvalidToken($user);

            return false;
        }

        // remove password field
        $userArray = Hash::remove($user->toArray(), $this->getConfig('fields.password'));

        return $userArray;
    }

    /**
     * Checks the fields to ensure they are supplied.
     *
     * @param ServerRequest $request The request that contains login information.
     * @return bool False if the fields have not been supplied. True if they exist.
     */
    protected function checkFields(ServerRequest $request)
    {
        $cookie = $this->getCookie($request);
        if (empty($cookie) || !is_string($cookie)) {
            return false;
        }

        $decoded = $this->decodeCookie($cookie);
        if (empty($decoded['username']) || empty($decoded['series']) || empty($decoded['token'])) {
            return false;
        }

        return true;
    }

    /**
     * get login token form cookie
     *
     * @param ServerRequest $request a Request instance
     * @return string
     */
    protected function getCookie(ServerRequest $request)
    {
        return $request->getCookie($this->getConfig('cookie.name'));
    }

    /**
     * set login token to cookie
     *
     * @param Response $response a Response instance.
     * @param string $cookie encrypted login token
     * @return Response
     */
    protected function setCookie(Response $response, $cookie)
    {
        $config = $this->getConfig('cookie');
        $expires = new FrozenTime($config['expires']);
        if (class_exists(Cookie::class)) {
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

        $config['value'] = $cookie;
        $config['expire'] = $expires->format('U');

        return $response->withCookie($this->getConfig('cookie.name'), $config);
    }

    /**
     * decode cookie
     *
     * @param string $cookie from request
     * @return array
     */
    public function decodeCookie($cookie)
    {
        return json_decode(Security::decrypt(base64_decode($cookie), Security::getSalt()), true);
    }

    /**
     * encode cookie
     *
     * @param string $username logged in user name
     * @param string $series series string
     * @param string $token login token
     * @return string
     */
    public function encryptToken($username, $series, $token)
    {
        return base64_encode(Security::encrypt(json_encode(compact('username', 'series', 'token')), Security::getSalt()));
    }

    /**
     * generate token
     *
     * @param array $user logged in user info
     * @return string
     */
    protected function generateToken(array $user)
    {
        $prefix = bin2hex(Security::randomBytes(16));
        $token = Security::hash($prefix . serialize($user));

        return $token;
    }

    /**
     * save login token to tokens table
     *
     * @param array $user logged in user info
     * @param string $token login token
     * @return EntityInterface|false
     */
    protected function saveToken(array $user, $token)
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
                'series' => $this->generateToken($user),
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
    protected function initializeUserModel()
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
     * @param string $token request token
     * @return EntityInterface|null
     */
    protected function findUserAndTokenBySeries($username, $series, $token = null)
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

        // change mappging
        $matchingData = $user->get('_matchingData');
        $user->set(static::$userTokenFieldName, $matchingData['RememberMeTokens']);
        $user->unsetProperty('_matchingData');

        return $user;
    }

    /**
     * verify user token, match and expires
     *
     * @param EntityInterface $user logged in user info
     * @param string $verifyToken token from cookie
     * @return bool
     */
    protected function verifyToken(EntityInterface $user, $verifyToken)
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
     * @param EntityInterface $user logged in user info
     * @return bool
     */
    protected function dropInvalidToken(EntityInterface $user)
    {
        $token = $this->getTokenFromUserEntity($user);

        return $this->getTokensTable()->delete($token);
    }

    /**
     * get token
     *
     * @param EntityInterface $user logged in user info
     * @return RememberMeToken
     * @throws InvalidArgumentException
     */
    protected function getTokenFromUserEntity(EntityInterface $user)
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
    public function implementedEvents()
    {
        return [
            'Auth.afterIdentify' => 'onAfterIdentify',
            'Auth.logout' => 'onLogout'
        ];
    }

    /**
     * event on 'Auth.afterIdentify'
     *
     * @param Event $event a Event instance
     * @param array $user logged in user info
     * @return void
     */
    public function onAfterIdentify(Event $event, array $user)
    {
        $authComponent = $event->getSubject();
        /* @var $authComponent AuthComponent */

        if (!$user) {
            // when authenticate failed, clear cookie token.
            $authComponent->response = $this->setCookie($authComponent->response, '');

            return;
        }

        if ($this->getConfig('dropExpiredToken')) {
            // drop expired token
            $this->getTokensTable()->dropExpired($this->getConfig('userModel'));
        }

        if ($this->getConfig('always') || $authComponent->request->getData($this->getConfig('inputKey'))) {
            // -- set token to cookie & session
            // save token
            $token = $this->saveToken($user, $this->generateToken($user));

            if ($token) {
                // write cookie
                $authComponent->response = $this->setLoginTokenToCookie($authComponent->response, $user, $token);
                // set token to user
                $user[static::$userTokenFieldName] = $token->toArray();

                return $user;
            }
        }
    }

    /**
     * Generate and set login token to Response
     *
     * @param Response $response a Response instance
     * @param array $user logged in user info
     * @param RememberMeToken $token a Token instance
     * @return Response
     */
    protected function setLoginTokenToCookie(Response $response, array $user, RememberMeToken $token)
    {
        if (isset($user[$this->getConfig('fields.username')])) {
            // write cookie
            $username = $user[$this->getConfig('fields.username')];
            $cookieToken = $this->encryptToken($username, $token->series, $token->token);
            $response = $this->setCookie($response, $cookieToken);
        }

        return $response;
    }

    /**
     * event on 'Auth.logout'
     *
     * @param Event $event a Event instance
     * @param array $user logged in user info
     * @return bool
     */
    public function onLogout(Event $event, array $user)
    {
        $authComponent = $event->getSubject();
        $authComponent->response = $this->setCookie($authComponent->response, '');

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
    protected function dropToken(array $user)
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
     * @return RememberMeToken|null
     */
    protected function getTokenFromUserArray(array $user)
    {
        if (empty($user[static::$userTokenFieldName])) {
            return null;
        }

        $tokenTable = $this->getTokensTable();
        $token = $tokenTable->find()
            ->where([
                'id' => $user[static::$userTokenFieldName]['id'],
            ])
            ->first();

        return $token;
    }

    /**
     * @return Table
     */
    protected function getUsersTable()
    {
        return TableRegistry::get($this->getConfig('userModel'));
    }

    /**
     * @return RememberMeTokensTable
     */
    protected function getTokensTable()
    {
        return TableRegistry::get($this->getConfig('tokenStorageModel'));
    }
}
