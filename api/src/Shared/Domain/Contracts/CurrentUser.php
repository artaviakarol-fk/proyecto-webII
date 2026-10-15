<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Contracts;

interface CurrentUser
{
    /** Identifier of the account that performs the current operation. */
    public function id(): string;
}
