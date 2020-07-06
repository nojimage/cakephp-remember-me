<?php

namespace RememberMe\Resolver;

use Authentication\Identifier\Resolver\ResolverInterface;
use Cake\Datasource\RepositoryInterface;

/**
 * TokenSeriesResolver Interface
 *
 * find user by username and token series
 */
interface TokenSeriesResolverInterface extends ResolverInterface
{
    /**
     * @return string
     */
    public function getUserTokenFieldName();

    /**
     * @return RepositoryInterface
     */
    public function getTokenStorage();
}
