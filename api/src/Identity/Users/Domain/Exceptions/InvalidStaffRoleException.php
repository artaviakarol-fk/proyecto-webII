<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final class InvalidStaffRoleException extends InvalidValueException
{
    /** The name is empty or too long. */
    public static function name(): self
    {
        return new self('identity.role_invalid_name');
    }

    /** The name is the one of a default role, which already exists in every negocio. */
    public static function reservedName(): self
    {
        return new self('identity.role_reserved_name');
    }

    /** A role without permissions would let its staff do nothing. */
    public static function noPermissions(): self
    {
        return new self('identity.role_without_permissions');
    }
}
