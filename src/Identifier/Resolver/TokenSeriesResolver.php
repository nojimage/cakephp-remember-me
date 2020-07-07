<?php

namespace RememberMe\Identifier\Resolver;

use Authentication\Identifier\Resolver\OrmResolver;

/**
 * Class TokenSeriesResolver
 */
class TokenSeriesResolver extends OrmResolver implements TokenSeriesResolverInterface
{
    /**
     * @inheritDoc
     */
    public function __construct(array $config = [])
    {
        // Override OrmResolver default configuration
        $this->_defaultConfig = array_merge(
            $this->_defaultConfig,
            [
                'tokenStorageModel' => 'RememberMe.RememberMeTokens',
                'userTokenFieldName' => 'remember_me_token',
            ]
        );

        parent::__construct($config);
    }

    /**
     * @inheritDoc
     */
    public function find(array $conditions, $type = self::TYPE_AND)
    {
        $this->initializeUserModel();

        $this->_config['finder'] = (array)$this->_config['finder'];
        $this->_config['finder']['WithRememberMeTokenBySeries'] = ['series' => $conditions['series']];
        unset($conditions['series']);

        return parent::find($conditions, $type);
    }

    /**
     * associate with RememberMeTokens to Users table
     *
     * @return void
     */
    protected function initializeUserModel()
    {
        $table = $this->getTableLocator()->get($this->getConfig('userModel'));
        if (!$table->hasBehavior('WithRememberMeTokenBySeries')) {
            $table->addBehavior('RememberMe.WithRememberMeTokenBySeries', [
                'tokenStorageModel' => $this->getConfig('tokenStorageModel'),
                'userTokenFieldName' => $this->getConfig('userTokenFieldName'),
                'userModel' => $this->getConfig('userModel'),
            ]);
        }
    }

    /**
     * @inheritDoc
     */
    public function getUserTokenFieldName()
    {
        return $this->getConfig('userTokenFieldName');
    }
}
