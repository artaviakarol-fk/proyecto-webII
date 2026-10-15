<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class Stock
{
    public const int MAX = 1_000_000;

    /** Accept a whole number of units that is never negative. */
    public function __construct(public int $units)
    {
        if ($units < 0 || $units > self::MAX) {
            throw new InvalidValueException('inventory.stock_out_of_range', ['max' => self::MAX]);
        }
    }

    /** Tell whether no unit is left. */
    public function isEmpty(): bool
    {
        return $this->units === 0;
    }
}
