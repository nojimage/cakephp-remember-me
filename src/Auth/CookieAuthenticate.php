<?php

namespace RememberMe\Auth;

use Cake\Auth\BaseAuthenticate;
use Cake\Event\Event;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\ORM\TableRegistry;
use Cake\I18n\Time;
use Cake\Utility\Security;

class CookieAuthenticate extends BaseAuthenticate
{

    public function __construct(\Cake\Controller\ComponentRegistry $registry, array $config = array())
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

    protected function _setCookie(Response $response, $cookie)
    {
        $config = $this->_config['cookie'];
        $expires = new Time($config['expires']);
        $config['value'] = $cookie;
        $config['expire'] = $expires->format('U');
        $response->cookie($config);
    }

    protected function _saveToken(array $user, $token)
    {
        $fields = $this->_config['fields'];
        $userTable = TableRegistry::get($this->_config['userModel']);
        $entity = $userTable->get($user[$userTable->primaryKey()]);
        $entity = $userTable->patchEntity($entity, [
            $fields['token'] => $this->passwordHasher()->hash($token),
        ]);
        return $userTable->save($entity);
    }

    protected function _getCookie(Request $request)
    {
        return $request->cookie($this->_config['cookie']['name']);
    }

    /**
     *
     * @param string $cookie
     * @return array
     */
    protected function _decodeCookie($cookie)
    {
        return json_decode(Security::decrypt($cookie, Security::salt()), true);
    }

    /**
     *
     * @param string $username
     * @param string $token
     * @return string
     */
    protected function _encodeCookie($username, $token)
    {
        return Security::encrypt(json_encode(compact('username', 'token')), Security::salt());
    }

    public function generateToken(array $user)
    {
        $token = Security::hash(serialize(microtime()) . serialize($user));
        return $token;
    }

    /**
     * Checks the fields to ensure they are supplied.
     *
     * @param \Cake\Network\Request $request The request that contains login information.
     * @return bool False if the fields have not been supplied. True if they exist.
     */
    protected function _checkFields(Request $request)
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

    public function authenticate(\Cake\Network\Request $request, \Cake\Network\Response $response)
    {
        return $this->getUser($request);
    }

    public function getUser(Request $request)
    {
        if (!$this->_checkFields($request)) {
            return false;
        }
        $user = $this->_decodeCookie($this->_getCookie($request));
        return $this->_findUser($user['username'], $user['token']);
    }

    protected function _findUser($username, $password = null)
    {
        $fields = $this->_config['fields'];
        $this->_config['fields']['password'] = $fields['token'];
        return parent::_findUser($username, $password);
    }

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
     * @param Event $event
     * @param array $user
     * @return boolean
     */
    public function onAfterIdentify(Event $event, array $user)
    {
        $authComponent = $event->subject();
        /* @var $authComponent \Cake\Controller\Component\AuthComponent */

        if (!$user) {
            // when authenticate failed, clear cookie token.
            $this->_setCookie($authComponent->response, '');
            return;
        }


        if (!$authComponent->request->data($this->_config['inputKey'])) {
            // nothing to do
            return;
        }

        $token = $this->generateToken($user);

        // save token
        $this->_saveToken($user, $token);

        // write cookie
        $username = $user[$this->_config['fields']['username']];
        $this->_setCookie($authComponent->response, $this->_encodeCookie($username, $token));
    }

    /**
     * event on 'Auth.logout'
     *
     * @param Event $event
     * @param array $user
     * @return boolean
     */
    public function onLogout(Event $event, array $user)
    {
        $authComponent = $event->subject();
        $this->_setCookie($authComponent->response, '');
        return true;
    }

}
