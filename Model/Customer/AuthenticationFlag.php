<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Customer;


class AuthenticationFlag
{
    /**
     * @var bool
     */
    private bool $registered = false;

    /**
     * Registration happened in this request
     *
     * @return void
     */
    public function markRegistered(): void
    {
        $this->registered = true;
    }

    /**
     * Whether a registration happened in this request
     *
     * @return bool
     */
    public function isRegistered(): bool
    {
        return $this->registered;
    }
}
