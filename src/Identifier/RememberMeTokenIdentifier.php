<?php

namespace RememberMe\Identifier;

use ArrayAccess;
use Authentication\Identifier\AbstractIdentifier;
use Authentication\Identifier\Resolver\ResolverAwareTrait;
use Authentication\Identifier\Resolver\ResolverInterface;
use RememberMe\Identifier\Resolver\TokenSeriesResolverInterface;
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
        if (is_string($config)) {
            $config = ['className' => $config];
        }
        if (!isset($config['userModel']) && $this->getConfig('userModel')) {
            $config['userModel'] = $this->getConfig('userModel');
        }

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
}
