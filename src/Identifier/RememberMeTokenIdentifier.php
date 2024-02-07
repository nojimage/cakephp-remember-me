<?php
declare(strict_types=1);

namespace RememberMe\Identifier;

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
 * @method \Authentication\Identifier\Resolver\OrmResolver getResolver()
 */
class RememberMeTokenIdentifier extends AbstractIdentifier
{
    use ResolverAwareTrait {
        buildResolver as traitBuildResolver;
    }

    protected const CREDENTIAL_SERIES = 'series';

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
     * @inheritDoc
     */
    protected function buildResolver($config): OrmResolver
    {
        $instance = $this->traitBuildResolver($config);

        if (!$instance instanceof OrmResolver) {
            $message = sprintf('Resolver must implement `%s`.', OrmResolver::class);
            throw new RuntimeException($message);
        }

        return $instance;
    }

    /**
     * @inheritDoc
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
     * @return \ArrayAccess|array|\Cake\Datasource\EntityInterface|null
     */
    protected function _findIdentity(string $identifier)
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
     * @param \Cake\Datasource\EntityInterface $identity the identity
     * @param string $series the credentials series
     * @return \Cake\Datasource\EntityInterface|null
     */
    protected function _findToken(EntityInterface $identity, string $series): ?EntityInterface
    {
        $userModel = $identity->getSource();
        if ($userModel === '') {
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
     * @param \Cake\Datasource\EntityInterface $token the remember-me token
     * @param string $verifyToken token from credentials
     * @return bool
     */
    protected function _verifyToken(EntityInterface $token, string $verifyToken): bool
    {
        if ($token['token'] !== $verifyToken) {
            $this->_errors[] = 'token does not match';

            return false;
        }

        if (FrozenTime::now()->greaterThan($token['expires'])) {
            $this->_errors[] = 'token expired';

            return false;
        }

        return true;
    }

    /**
     * drop invalid token
     *
     * @param \Cake\Datasource\EntityInterface $token the remember-me token
     * @return bool
     */
    protected function _dropInvalidToken(EntityInterface $token): bool
    {
        $tokenStorageTable = $this->getResolver()->getTableLocator()->get($this->getConfig('tokenStorageModel'));

        return $tokenStorageTable->delete($token);
    }
}
