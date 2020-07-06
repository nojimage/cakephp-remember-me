<?php

namespace RememberMe\Resolver;

use Authentication\Identifier\Resolver\OrmResolver;

/**
 * Class TokenSeriesResolver
 *
 * find user by username and token series
 */
class TokenSeriesResolver extends OrmResolver
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
            $table->addBehavior('RememberMe.WithRememberMeTokenBySeries', $this->getConfig());
        }
    }
}
