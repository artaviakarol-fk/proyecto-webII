<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\ForbiddenException;

final class PasswordChangeRequiredException extends ForbiddenException
{
    /** The account still has the temporary password that somebody else chose for it. */
    public static function create(): self
    {
        return new self('identity.password_change_required');
    }
}
