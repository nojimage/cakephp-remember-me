<?php
declare(strict_types=1);

namespace RememberMe\Model\Table;

/**
 * RememberMeTokensTableInterface
 */
interface RememberMeTokensTableInterface
{
    /**
     * drop expired tokens
     *
     * @param string|null $userModel target user model
     * @param string|null $foreignId target user id
     * @return int
     */
    public function dropExpired(?string $userModel = null, ?string $foreignId = null): int;
}
