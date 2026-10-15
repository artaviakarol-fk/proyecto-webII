<?php

declare(strict_types=1);

namespace Src\Inventory\Receipts\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class ReceiptNotFoundException extends NotFoundException
{
    /** Build the exception for a receipt missing from the negocio. */
    public static function create(): self
    {
        return new self('inventory.receipt_not_found');
    }
}
