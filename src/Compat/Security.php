<?php

namespace RememberMe\Compat;

use Cake\Utility\Security as CoreSecurity;

/**
 * Security class for CakePHP 3.6 compatibility
 *
 * getSalt/setSalt
 */
class Security extends CoreSecurity
{
    /**
     * Gets the HMAC salt to be used for encryption/decryption routines.
     *
     * @return string The currently configured salt
     * @see Cake\Utility\Security::getSalt()
     */
    public static function getSalt()
    {
        if (method_exists(CoreSecurity::class, 'getSalt')) {
            return parent::getSalt();
        }

        return parent::salt();
    }

    /**
     * Sets the HMAC salt to be used for encryption/decryption routines.
     *
     * @param string $salt The salt to use for encryption routines.
     * @return string The currently configured salt
     * @see Cake\Utility\Security::setSalt()
     */
    public static function setSalt($salt)
    {
        if (method_exists(CoreSecurity::class, 'getSalt')) {
            return parent::setSalt($salt);
        }

        return parent::salt($salt);
    }
}
