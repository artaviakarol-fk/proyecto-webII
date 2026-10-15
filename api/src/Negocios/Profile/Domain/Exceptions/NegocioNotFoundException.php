<?php

declare(strict_types=1);

namespace Src\Negocios\Profile\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class NegocioNotFoundException extends NotFoundException
{
    /** Build the exception for a negocio that does not exist. */
    public static function create(): self
    {
        return new self('negocios.not_found');
    }
}
