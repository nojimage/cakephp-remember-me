<?php

namespace RememberMe\Model\Behavior;

use Cake\Datasource\EntityInterface;
use Cake\Datasource\ResultSetInterface;
use Cake\ORM\Behavior;
use Cake\ORM\Query;
use InvalidArgumentException;

/**
 * provide findWithRememberMeTokenBySeries
 */
class WithRememberMeTokenBySeriesBehavior extends Behavior
{
    protected $_defaultConfig = [
        'tokenStorageModel' => 'RememberMe.RememberMeTokens',
        'userTokenFieldName' => 'remember_me_token',
    ];

    /**
     * @inheritDoc
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $table = $this->_table;
        if ($table->associations() && !$table->associations()->has('RememberMeTokens')) {
            $table->hasMany('RememberMeTokens', [
                'className' => $this->getConfig('tokenStorageModel'),
                'foreignKey' => 'foreign_id',
                'conditions' => ['RememberMeTokens.model' => $this->getConfig('userModel')],
                'dependent' => true,
            ]);
        }
    }

    /**
     * @param Query $query the Query
     * @param array $options ['series' => ...]
     * @return Query
     */
    public function findWithRememberMeTokenBySeries(Query $query, array $options)
    {
        if (!isset($options['series'])) {
            throw new InvalidArgumentException(sprintf('find(\'WithRememberMeTokenSeries\') required `series` option.'));
        }

        $series = $options['series'];

        if (!empty($query->clause('select'))) {
            $query->select($this->_table->RememberMeTokens);
        }

        $query
            ->matching('RememberMeTokens', static function (Query $q) use ($series) {
                return $q->where(['RememberMeTokens.series' => $series]);
            })
            ->formatResults(function (ResultSetInterface $results) {
                return $results->map(function (EntityInterface $user) {
                    // change mapping
                    $matchingData = $user->get('_matchingData');
                    $user->set($this->getConfig('userTokenFieldName'), $matchingData['RememberMeTokens']);
                    $user->unsetProperty('_matchingData');

                    return $user;
                });
            });

        return $query;
    }
}
