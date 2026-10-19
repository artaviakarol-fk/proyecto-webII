<?php

declare(strict_types=1);

namespace Src\Negocios\Profile\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class LogoNotFoundException extends NotFoundException
{
    /** Build the exception for a negocio that has no logo yet. */
    public static function create(): self
    {
        return new self('negocios.logo_not_found');
    }
}
