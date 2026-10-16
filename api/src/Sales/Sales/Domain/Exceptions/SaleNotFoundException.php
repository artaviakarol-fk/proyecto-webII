<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class SaleNotFoundException extends NotFoundException
{
    /** Build the exception for a sale missing from the negocio. */
    public static function create(): self
    {
        return new self('sales.sale_not_found');
    }
}
