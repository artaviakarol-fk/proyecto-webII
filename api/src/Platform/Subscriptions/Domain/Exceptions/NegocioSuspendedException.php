<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\ForbiddenException;

final class NegocioSuspendedException extends ForbiddenException
{
    /** The service of the negocio is suspended, so its staff and its clientes cannot use it. */
    public static function create(): self
    {
        return new self('platform.suspended');
    }

    /** The administrator of a suspended negocio can see its data and pay, but not change anything. */
    public static function readOnly(): self
    {
        return new self('platform.suspended_read_only');
    }
}
