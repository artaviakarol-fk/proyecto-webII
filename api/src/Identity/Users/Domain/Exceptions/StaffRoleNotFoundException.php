<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class StaffRoleNotFoundException extends NotFoundException
{
    /** The role does not exist in the negocio. */
    public static function create(): self
    {
        return new self('identity.role_not_found');
    }
}
