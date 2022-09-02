<?php

namespace RememberMe\Identifier;

use ArrayAccess;
use Authentication\Identifier\AbstractIdentifier;
use Authentication\Identifier\Resolver\OrmResolver;
use Authentication\Identifier\Resolver\ResolverAwareTrait;
use Authentication\Identifier\Resolver\ResolverInterface;
use Cake\Datasource\EntityInterface;
use Cake\I18n\FrozenTime;
use InvalidArgumentException;
use RuntimeException;

/**
 * Class RememberMeTokenIdentifier
 *
 * @method OrmResolver getResolver()
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
     * - `resolver` The resolver implementation to use. the class must be
     *   OrmResolver or the inherited class.
     * - `tokenStorageModel`: A model used for storing login cookie tokens.
     * - `userTokenFieldName`: A property name when adding token data to identity.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'fields' => [
            self::CREDENTIAL_USERNAME => 'username',
        ],
        'resolver' => 'Authentication.Orm',
        'tokenStorageModel' => 'RememberMe.RememberMeTokens',
        'userTokenFieldName' => 'remember_me_token',
    ];

    /**
     * {@inheritDoc}
     */
    protected function buildResolver($config)
    {
        $instance = $this->traitBuildResolver($config);

        if (!$instance instanceof OrmResolver) {
            $message = sprintf('Resolver must implement `%s`.', OrmResolver::class);
            throw new RuntimeException($message);
        }

        return $instance;
    }

    /**
     * {@inheritDoc}
     */
    public function identify(array $credentials)
    {
        if (
            !isset(
                $credentials[self::CREDENTIAL_USERNAME],
                $credentials[self::CREDENTIAL_SERIES],
                $credentials[self::CREDENTIAL_TOKEN]
            )
        ) {
            return null;
        }

        $identity = $this->_findIdentity($credentials[self::CREDENTIAL_USERNAME]);

        if (!$identity instanceof EntityInterface) {
            return null;
        }

        $token = $this->_findToken($identity, $credentials[self::CREDENTIAL_SERIES]);

        if ($token === null) {
            return null;
        }

        if (!$this->_verifyToken($token, $credentials[self::CREDENTIAL_TOKEN])) {
            $this->_dropInvalidToken($token);

            return null;
        }

        $identity->set($this->getConfig('userTokenFieldName'), $token);

        return $identity;
    }

    /**
     * Find a user record using the username/identifier provided.
     *
     * @param string $identifier The username/identifier.
     * @return ArrayAccess|array|null
     */
    protected function _findIdentity($identifier)
    {
        $fields = $this->getConfig('fields.' . self::CREDENTIAL_USERNAME);
        $conditions = [];
        foreach ((array)$fields as $field) {
            $conditions[$field] = $identifier;
        }

        return $this->getResolver()->find($conditions, ResolverInterface::TYPE_OR);
    }

    /**
     * find user's remember me token.
     *
     * @param EntityInterface $identity the identity
     * @param string $series the credentials series
     * @return EntityInterface|null
     */
    protected function _findToken(EntityInterface $identity, $series)
    {
        $userModel = $identity->getSource();
        if ($userModel === null) {
            throw new InvalidArgumentException('Can\'t get user model from identity.');
        }

        $usersTable = $this->getResolver()->getTableLocator()->get($userModel);
        $tokenStorageTable = $this->getResolver()->getTableLocator()->get($this->getConfig('tokenStorageModel'));

        return $tokenStorageTable->find()
            ->where([
                'model' => $userModel,
                'foreign_id' => $identity->get($usersTable->getPrimaryKey()),
                'series' => $series,
            ])
            ->first();
    }

    /**
     * verify user token, match and expires
     *
     * @param EntityInterface $token the remember me token
     * @param string $verifyToken token from credentials
     * @return bool
     */
    protected function _verifyToken(EntityInterface $token, $verifyToken)
    {
        if ($token['token'] !== $verifyToken) {
            $this->_errors[] = 'token does not match';

            return false;
        }

        if (FrozenTime::now()->gt($token['expires'])) {
            $this->_errors[] = 'token expired';

            return false;
        }

        return true;
    }

    /**
     * drop invalid token
     *
     * @param EntityInterface $token the remember-me token
     * @return bool
     */
    protected function _dropInvalidToken($token)
    {
        $tokenStorageTable = $this->getResolver()->getTableLocator()->get($this->getConfig('tokenStorageModel'));

        return $tokenStorageTable->delete($token);
    }
}
