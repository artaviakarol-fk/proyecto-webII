<?php

declare(strict_types=1);

namespace Src\Inventory\Expirations\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class ExpiredQuantity
{
    public const int MAX = 1_000_000;

    /** Accept a whole number of units between one and the maximum. */
    public function __construct(public int $value)
    {
        if ($value < 1 || $value > self::MAX) {
            throw new InvalidValueException('inventory.expired_quantity_out_of_range', ['max' => self::MAX]);
        }
    }
}
