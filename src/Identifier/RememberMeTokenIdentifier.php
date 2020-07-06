<?php

namespace RememberMe\Identifier;

use ArrayAccess;
use Authentication\Identifier\AbstractIdentifier;
use Authentication\Identifier\Resolver\ResolverAwareTrait;
use Authentication\Identifier\Resolver\ResolverInterface;
use Cake\I18n\FrozenTime;
use InvalidArgumentException;
use RememberMe\Model\Entity\RememberMeToken;
use RememberMe\Resolver\TokenSeriesResolverInterface;
use RuntimeException;

/**
 * Class RememberMeTokenIdentifier
 *
 * @method TokenSeriesResolverInterface getResolver()
 */
class RememberMeTokenIdentifier extends AbstractIdentifier
{
    use ResolverAwareTrait {
        buildResolver as traitBuildResolver;
    }

    const CREDENTIAL_SERIES = 'series';

    /**
     * Default configuration.
     * - `fields` The fields to use to identify a user by:
     *   - `username`: one or many username fields.
     *   - `series`: series field.
     *   - `token`: token field.
     * - `resolver` The resolver implementation to use. the class must be
     *   TokenSeriesResolver or the inherited class.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'fields' => [
            self::CREDENTIAL_USERNAME => 'username',
            self::CREDENTIAL_SERIES => 'series',
            self::CREDENTIAL_TOKEN => 'token',
        ],
        'resolver' => 'RememberMe.TokenSeries',
    ];

    /**
     * @inheritDoc
     */
    protected function buildResolver($config)
    {
        $instance = $this->traitBuildResolver($config);

        if (!($instance instanceof TokenSeriesResolverInterface)) {
            $message = sprintf('Resolver must implement `%s`.', TokenSeriesResolverInterface::class);
            throw new RuntimeException($message);
        }

        return $instance;
    }

    /**
     * {@inheritDoc}
     */
    public function identify(array $credentials)
    {
        if (!isset(
            $credentials[self::CREDENTIAL_USERNAME],
            $credentials[self::CREDENTIAL_SERIES],
            $credentials[self::CREDENTIAL_TOKEN]
        )) {
            return null;
        }

        $identity = $this->_findIdentity($credentials[self::CREDENTIAL_USERNAME], $credentials[self::CREDENTIAL_SERIES]);

        if ($identity === null) {
            return null;
        }

        if (!$this->_verifyToken($identity, $credentials[self::CREDENTIAL_TOKEN])) {
            $this->_dropInvalidToken($identity);

            return null;
        }

        return $identity;
    }

    /**
     * Find a user record using the username/identifier provided.
     *
     * @param string $identifier The username/identifier.
     * @param string $series The token series
     * @return ArrayAccess|array|null
     */
    protected function _findIdentity($identifier, $series)
    {
        $fields = $this->getConfig('fields.' . self::CREDENTIAL_USERNAME);
        $conditions = compact('series');
        foreach ((array)$fields as $field) {
            $conditions[$field] = $identifier;
        }

        return $this->getResolver()->find($conditions, ResolverInterface::TYPE_OR);
    }

    /**
     * verify user token, match and expires
     *
     * @param ArrayAccess|array $identifier the user info
     * @param string $verifyToken token from cookie
     * @return bool
     */
    protected function _verifyToken($identifier, $verifyToken)
    {
        $token = $this->_getTokenFromIdentifier($identifier);

        if ($token['token'] !== $verifyToken) {
            return false;
        }

        if (FrozenTime::now()->gt($token['expires'])) {
            return false;
        }

        return true;
    }

    /**
     * get token from identifier
     *
     * @param ArrayAccess|array $identifier the user info
     * @return RememberMeToken|ArrayAccess|array
     * @throws InvalidArgumentException
     */
    protected function _getTokenFromIdentifier($identifier)
    {
        $tokenField = $this->getResolver()->getUserTokenFieldName();

        if (!isset($identifier[$tokenField])) {
            throw new InvalidArgumentException('user entity has not matching token data.');
        }

        return $identifier[$tokenField];
    }

    /**
     * drop invalid token
     *
     * @param ArrayAccess|array $identifier the user info
     * @return bool
     */
    protected function _dropInvalidToken($identifier)
    {
        $token = $this->_getTokenFromIdentifier($identifier);

        return $this->getResolver()->getTokenStorage()->delete($token);
    }
}
