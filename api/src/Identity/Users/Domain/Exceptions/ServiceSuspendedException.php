<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\ForbiddenException;

final class ServiceSuspendedException extends ForbiddenException
{
    /** The service of the negocio is suspended: only its administrators can enter, to see and to pay. */
    public static function create(): self
    {
        return new self('platform.suspended');
    }
}
