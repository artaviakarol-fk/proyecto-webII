<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class SaleProductUnavailableException extends DomainException
{
    /** Build the exception for a product that disappeared from the catalog while the sale was being made. */
    public static function create(): self
    {
        return new self('sales.product_unavailable');
    }
}
