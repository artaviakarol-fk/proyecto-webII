<?php

declare(strict_types=1);

namespace Src\Inventory\Receipts\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final class StockLimitExceededException extends InvalidValueException
{
    /** Build the exception for a receipt that would leave a product above the stock limit. */
    public static function create(int $max): self
    {
        return new self('inventory.stock_limit_exceeded', ['max' => $max]);
    }
}
