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
use Cake\ORM\TableRegistry;
use Cake\Utility\Security;

/**
 * Cookie Authenticate
 */
class CookieAuthenticate extends BaseAuthenticate
{

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
                'token' => 'login_cookie',
            ],
            'inputKey' => 'remember_me',
            'cookie' => [
                'name' => 'rememberMe',
                'expires' => '+30 days',
                'secure' => false,
                'httpOnly' => true,
            ],
            'cookieLifeTime' => '+30 days',
            'userModel' => 'Users',
            'scope' => [],
            'contain' => null,
            'passwordHasher' => 'Default'
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
    protected function _setCookie(Response $response, $cookie)
    {
        $config = $this->getConfig('cookie');
        $expires = new FrozenTime($config['expires']);
        $config['value'] = $cookie;
        $config['expire'] = $expires->format('U');
        return $response->withCookie($config);
    }

    /**
     * save login token to users table
     *
     * @param array $user logged in user info
     * @param string $token login token
     * @return EntityInterface|false
     */
    protected function _saveToken(array $user, $token)
    {
        $fields = $this->getConfig('fields');
        $userTable = TableRegistry::get($this->getConfig('userModel'));
        $entity = $userTable->get($user[$userTable->primaryKey()]);
        $userTable->patchEntity($entity, [
            $fields['token'] => $this->passwordHasher()->hash($token),
        ]);

        return $userTable->save($entity);
    }

    /**
     * get login token form cookie
     *
     * @param ServerRequest $request a Request instance
     * @return string
     */
    protected function _getCookie(ServerRequest $request)
    {
        return $request->getCookie($this->getConfig('cookie.name'));
    }

    /**
     * decode cookie
     *
     * @param string $cookie from request
     * @return array
     */
    protected function _decodeCookie($cookie)
    {
        return json_decode(Security::decrypt($cookie, Security::salt()), true);
    }

    /**
     * encode cookie
     *
     * @param string $username logged in user name
     * @param string $token login token
     * @return string
     */
    protected function _encodeCookie($username, $token)
    {
        return Security::encrypt(json_encode(compact('username', 'token')), Security::salt());
    }

    /**
     * generate login token
     *
     * @param array $user logged in user info
     * @return string
     */
    public function generateToken(array $user)
    {
        $token = Security::hash(serialize(microtime()) . serialize($user));

        return $token;
    }

    /**
     * Checks the fields to ensure they are supplied.
     *
     * @param ServerRequest $request The request that contains login information.
     * @return bool False if the fields have not been supplied. True if they exist.
     */
    protected function _checkFields(ServerRequest $request)
    {
        $cookie = $this->_getCookie($request);
        if (empty($cookie) || !is_string($cookie)) {
            return false;
        }
        $user = $this->_decodeCookie($cookie);
        if (empty($user['username']) || empty($user['token'])) {
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
        if (!$this->_checkFields($request)) {
            return false;
        }
        $user = $this->_decodeCookie($this->_getCookie($request));

        return $this->_findUser($user['username'], $user['token']);
    }

    /**
     * find user with username and login token
     *
     * @param string $username request username
     * @param string $password request token
     * @return array
     */
    protected function _findUser($username, $password = null)
    {
        $this->setConfig('fields.password', $this->getConfig('fields.token'));

        return parent::_findUser($username, $password);
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
            $authComponent->response = $this->_setCookie($authComponent->response, '');

            return;
        }

        if (!$authComponent->request->getData($this->getConfig('inputKey'))) {
            // nothing to do
            return;
        }

        $token = $this->generateToken($user);

        // save token
        $this->_saveToken($user, $token);

        // write cookie
        $username = $user[$this->getConfig('fields.username')];
        $authComponent->response = $this->_setCookie($authComponent->response, $this->_encodeCookie($username, $token));
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
        $this->_setCookie($authComponent->response, '');

        return true;
    }
}
