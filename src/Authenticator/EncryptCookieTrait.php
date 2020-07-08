<?php

namespace RememberMe\Authenticator;

use ArrayAccess;
use InvalidArgumentException;
use RememberMe\Compat\Security;

/**
 * Encrypt Cookie Utility
 */
trait EncryptCookieTrait
{
    /**
     * decode cookie
     *
     * @param string $cookie from request
     * @return array
     * @throws InvalidArgumentException
     */
    public static function decodeCookie($cookie)
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
    public static function encryptToken($username, $series, $token)
    {
        return base64_encode(Security::encrypt(json_encode(compact('username', 'series', 'token')), Security::getSalt()));
    }

    /**
     * generate token
     *
     * @param ArrayAccess|array $identity logged in user info
     * @return string
     */
    protected static function _generateToken($identity)
    {
        $prefix = bin2hex(Security::randomBytes(16));

        return Security::hash($prefix . serialize($identity));
    }
}
