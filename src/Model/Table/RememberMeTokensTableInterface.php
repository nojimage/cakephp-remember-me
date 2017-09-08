<?php

namespace RememberMe\Model\Table;

/**
 * RememberMeTokensTableInterface
 */
interface RememberMeTokensTableInterface
{

    /**
     * drop expired tokens
     *
     * @param string $userModel target user model
     * @param string $foreignId target user id
     * @return bool
     */
    public function dropExpired($userModel = null, $foreignId = null);
}
