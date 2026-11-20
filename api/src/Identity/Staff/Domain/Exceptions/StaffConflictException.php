<?php

declare(strict_types=1);

namespace Src\Identity\Staff\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class StaffConflictException extends DomainException
{
    /** An administrator cannot take away its own role or deactivate itself; another administrator can. */
    public static function ownAccount(): self
    {
        return new self('identity.own_account');
    }

    /** The change would leave the negocio without an active administrator. */
    public static function lastAdministrator(): self
    {
        return new self('identity.last_administrator');
    }
}
