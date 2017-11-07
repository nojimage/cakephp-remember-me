<?php

namespace RememberMe\Test\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\Table;

/**
 * AuthUsers table for test
 */
class AuthUsersTable extends Table
{

    /**
     * @param Query $query
     * @return Query
     */
    public function findOnlyUsername(Query $query)
    {
        return $query->select(['username']);
    }
}
