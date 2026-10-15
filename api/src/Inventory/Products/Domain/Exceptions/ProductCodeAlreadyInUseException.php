<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final class ProductCodeAlreadyInUseException extends InvalidValueException
{
    /** Build the exception for a code taken by another product of the negocio. */
    public static function create(): self
    {
        return new self('inventory.code_in_use');
    }
}
