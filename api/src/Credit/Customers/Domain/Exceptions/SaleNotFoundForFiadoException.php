<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class SaleNotFoundForFiadoException extends NotFoundException
{
    /** Build the exception for a sale missing from the negocio. */
    public static function create(): self
    {
        return new self('credit.sale_not_found');
    }
}
