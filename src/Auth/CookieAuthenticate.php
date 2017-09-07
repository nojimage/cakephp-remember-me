<?php

namespace RememberMe\Auth;

use Cake\Auth\BaseAuthenticate;
use Cake\Controller\ComponentRegistry;
use Cake\Controller\Component\AuthComponent;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\I18n\FrozenTime;
use Cake\ORM\Query;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Utility\Security;
use InvalidArgumentException;
use RememberMe\Model\Entity\RememberMeToken;

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
        $this->config([
            'fields' => [
                'username' => 'username',
            ],
            'inputKey' => 'remember_me',
            'always' => false,
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
        $config['value'] = $cookie;
        $config['expire'] = $expires->format('U');

        return $response->withCookie($this->getConfig('cookie.name'), $config);
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
        $fields = $this->getConfig('fields');
        $userModel = $this->getConfig('userModel');
        $userTable = TableRegistry::get($userModel);
        $tokenTable = TableRegistry::get($this->getConfig('tokenStorageModel'));

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
     * decode cookie
     *
     * @param string $cookie from request
     * @return array
     */
    public function decodeCookie($cookie)
    {
        return json_decode(Security::decrypt($cookie, Security::salt()), true);
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
        return Security::encrypt(json_encode(compact('username', 'series', 'token')), Security::salt());
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
            $this->dropToken($user);

            return false;
        }

        // remove password field
        $userArray = Hash::remove($user->toArray(), $this->getConfig('fields.password'));
        $userArray[static::$userTokenFieldName] = array_intersect_key($userArray['_matchingData']['RememberMeTokens'], [
            'id' => true,
            'series' => true,
        ]);
        unset($userArray['_matchingData']);

        return $userArray;
    }

    /**
     * associate with RememberMeTokens to Users table
     *
     * @return void
     */
    protected function initializeUserModel()
    {
        $userModel = $this->getConfig('userModel');

        $table = TableRegistry::get($userModel);
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
     * @return EntityInterface
     */
    protected function findUserAndTokenBySeries($username, $series, $token = null)
    {
        $this->initializeUserModel();

        $query = $this->_query($username);
        $query->matching('RememberMeTokens', function (Query $q) use ($series) {
            return $q->where(['RememberMeTokens.series' => $series]);
        });

        return $query->first();
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
    protected function dropToken(EntityInterface $user)
    {
        $token = $this->getTokenFromUserEntity($user);
        $tokenTable = TableRegistry::get($this->getConfig('tokenStorageModel'));

        return $tokenTable->delete($token);
    }

    /**
     * get token
     *
     * @param EntityInterface $user logged in user info
     * @return RememberMeToken
     * @throws InvalidArgumentException
     */
    private function getTokenFromUserEntity(EntityInterface $user)
    {
        if (empty($user->_matchingData) || empty($user->_matchingData['RememberMeTokens'])) {
            throw new InvalidArgumentException('user entity has not matching token data.');
        }

        return $user->_matchingData['RememberMeTokens'];
    }

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
        if (!empty($user[static::$userTokenFieldName])) {
            $tokenTable = TableRegistry::get($this->getConfig('tokenStorageModel'));
            $token = $tokenTable->find()->where([
                'id' => $user[static::$userTokenFieldName]['id'],
            ])->first();

            if ($token) {
                $tokenTable->delete($token);
            }
        }

        return true;
    }
}
