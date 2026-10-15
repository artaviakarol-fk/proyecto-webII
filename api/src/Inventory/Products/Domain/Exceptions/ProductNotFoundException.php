<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class ProductNotFoundException extends NotFoundException
{
    /** Build the exception for a product missing from the negocio. */
    public static function create(): self
    {
        return new self('inventory.product_not_found');
    }
}
