<?php

declare(strict_types=1);

namespace Src\Inventory\Expirations\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class ExpiredUnitsExceedSellableException extends DomainException
{
    /** Build the exception for more expired units than the product has left without a mark. */
    public static function create(int $quantity, int $available): self
    {
        return new self('inventory.expired_units_exceed_sellable', ['quantity' => $quantity, 'available' => $available]);
    }
}
