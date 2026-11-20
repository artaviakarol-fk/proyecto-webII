<?php

declare(strict_types=1);

namespace Src\Platform\Tenants\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class TenantNotFoundException extends NotFoundException
{
    /** The negocio does not exist on the platform. */
    public static function create(): self
    {
        return new self('negocios.not_found');
    }
}
