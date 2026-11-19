<?php

declare(strict_types=1);

namespace Src\Identity\Staff\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class StaffNotFoundException extends NotFoundException
{
    /** The account is not of the staff of the negocio. */
    public static function create(): self
    {
        return new self('identity.staff_not_found');
    }
}
