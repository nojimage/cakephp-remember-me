<?php

namespace RememberMe\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\I18n\FrozenTime;
use Cake\ORM\Behavior\TimestampBehavior;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use RememberMe\Model\Entity\RememberMeToken;

/**
 * RememberMeTokens Model
 *
 * @method RememberMeToken get($primaryKey, $options = [])
 * @method RememberMeToken newEntity($data = null, array $options = [])
 * @method RememberMeToken[] newEntities(array $data, array $options = [])
 * @method RememberMeToken|bool save(EntityInterface $entity, $options = [])
 * @method RememberMeToken patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method RememberMeToken[] patchEntities($entities, array $data, array $options = [])
 * @method RememberMeToken findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin TimestampBehavior
 */
class RememberMeTokensTable extends Table implements RememberMeTokensTableInterface
{
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->setTable('remember_me_tokens');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
    }

    /**
     * Default validation rules.
     *
     * @param Validator $validator Validator instance.
     * @return Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->integer('id')
            ->allowEmpty('id', 'create');

        $validator
            ->requirePresence('model', 'create')
            ->notEmpty('model');

        $validator
            ->requirePresence('foreign_id', 'create')
            ->notEmpty('foreign_id');

        $validator
            ->requirePresence('series', 'create')
            ->notEmpty('series');

        $validator
            ->requirePresence('token', 'create')
            ->notEmpty('token');

        $validator
            ->dateTime('expires')
            ->requirePresence('expires', 'create')
            ->notEmpty('expires');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param RulesChecker $rules The rules object to be modified.
     * @return RulesChecker
     */
    public function buildRules(RulesChecker $rules)
    {
        $rules->add($rules->isUnique(['model', 'foreign_id', 'series']));

        return $rules;
    }

    /**
     * drop expired tokens
     *
     * @param string $userModel target user model
     * @param string $foreignId target user id
     * @return bool
     */
    public function dropExpired($userModel = null, $foreignId = null)
    {
        $conditions = [
            $this->aliasField('expires') . ' <' => FrozenTime::now(),
        ];
        if (!is_null($userModel)) {
            $conditions[$this->aliasField('model')] = $userModel;
        }
        if (!is_null($foreignId)) {
            $conditions[$this->aliasField('foreign_id')] = $foreignId;
        }

        return $this->deleteAll($conditions);
    }
}
