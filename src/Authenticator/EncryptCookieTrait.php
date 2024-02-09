<?php
declare(strict_types=1);

namespace RememberMe\Authenticator;

use ArrayAccess;
use Cake\Utility\Security;
use InvalidArgumentException;

/**
 * Encrypt Cookie Utility
 */
trait EncryptCookieTrait
{
    /**
     * decode cookie
     *
     * @param string $cookie from request
     * @return array ['username' => ..., 'series' => ..., 'token' => ...]
     * @throws \InvalidArgumentException
     * @throws \JsonException
     */
    public static function decodeCookie(string $cookie): array
    {
        $decryptedValue = Security::decrypt(base64_decode($cookie), Security::getSalt());
        if ($decryptedValue === null) {
            throw new InvalidArgumentException('Can\'t decrypt cookie.');
        }

        return json_decode($decryptedValue, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * encode cookie
     *
     * @param string $username logged in user name
     * @param string $series series string
     * @param string $token login token
     * @return string
     * @throws \JsonException
     */
    public static function encryptToken(string $username, string $series, string $token): string
    {
        return base64_encode(
            Security::encrypt(
                json_encode(compact('username', 'series', 'token'), JSON_THROW_ON_ERROR),
                Security::getSalt()
            )
        );
    }

    /**
     * generate token
     *
     * @param \ArrayAccess|array $identity logged in user info
     * @return string
     */
    protected static function _generateToken(ArrayAccess|array $identity): string
    {
        $prefix = bin2hex(Security::randomBytes(16));

        return Security::hash($prefix . serialize($identity));
    }
}
