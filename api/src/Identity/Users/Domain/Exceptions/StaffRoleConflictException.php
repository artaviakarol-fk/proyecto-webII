<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class StaffRoleConflictException extends DomainException
{
    /** The negocio already has a role with that name. */
    public static function nameTaken(): self
    {
        return new self('identity.role_name_taken');
    }

    /** Somebody of the staff still has the role, so it cannot be deleted. */
    public static function inUse(): self
    {
        return new self('identity.role_in_use');
    }

    /** Taking the permission to administrate from the role would leave the negocio without anybody who manages it. */
    public static function lastAdministrator(): self
    {
        return new self('identity.last_administrator');
    }
}
