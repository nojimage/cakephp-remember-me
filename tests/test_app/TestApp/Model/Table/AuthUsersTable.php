<?php
declare(strict_types=1);

namespace TestApp\Model\Table;

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
    public function findOnlyUsername(Query $query): Query
    {
        return $query->select(['username']);
    }
}
