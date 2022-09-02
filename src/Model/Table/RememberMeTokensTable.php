<?php
declare(strict_types=1);

namespace RememberMe\Model\Table;

use Cake\I18n\FrozenTime;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * RememberMeTokens Model
 *
 * @method \RememberMe\Model\Entity\RememberMeToken get($primaryKey, $options = [])
 * @method \RememberMe\Model\Entity\RememberMeToken newEntity($data = null, array $options = [])
 * @method \RememberMe\Model\Entity\RememberMeToken[] newEntities(array $data, array $options = [])
 * @method \RememberMe\Model\Entity\RememberMeToken|false save(\RememberMe\Model\Table\EntityInterface $entity, $options = [])
 * @method \RememberMe\Model\Entity\RememberMeToken patchEntity(\RememberMe\Model\Table\EntityInterface $entity, array $data, array $options = [])
 * @method \RememberMe\Model\Entity\RememberMeToken[] patchEntities($entities, array $data, array $options = [])
 * @method \RememberMe\Model\Table\RememberMeToken findOrCreate($search, callable $callback = null, $options = [])
 * @mixin \RememberMe\Model\Table\TimestampBehavior
 */
class RememberMeTokensTable extends Table implements RememberMeTokensTableInterface
{
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
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
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', 'create');

        $validator
            ->requirePresence('model', 'create')
            ->allowEmptyString('model');

        $validator
            ->requirePresence('foreign_id', 'create')
            ->allowEmptyString('foreign_id');

        $validator
            ->requirePresence('series', 'create')
            ->allowEmptyString('series');

        $validator
            ->requirePresence('token', 'create')
            ->allowEmptyString('token');

        $validator
            ->dateTime('expires')
            ->requirePresence('expires', 'create')
            ->allowEmptyDateTime('expires');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['model', 'foreign_id', 'series']));

        return $rules;
    }

    /**
     * drop expired tokens
     *
     * @param string|null $userModel target user model
     * @param string|int|null $foreignId target user id
     * @return int the dropped token count
     */
    public function dropExpired(?string $userModel = null, $foreignId = null): int
    {
        $conditions = [
            $this->aliasField('expires <') => FrozenTime::now(),
        ];
        if ($userModel !== null) {
            $conditions[$this->aliasField('model')] = $userModel;
        }
        if ($foreignId !== null) {
            $conditions[$this->aliasField('foreign_id')] = $foreignId;
        }

        return $this->deleteAll($conditions);
    }
}
