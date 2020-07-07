<?php

namespace RememberMe\Identifier\Resolver;

use Authentication\Identifier\Resolver\ResolverInterface;

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
}
