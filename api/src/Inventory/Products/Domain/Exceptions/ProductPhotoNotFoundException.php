<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class ProductPhotoNotFoundException extends NotFoundException
{
    /** Build the exception for a product that has no photo yet. */
    public static function create(): self
    {
        return new self('inventory.photo_not_found');
    }
}
