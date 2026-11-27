<?php

declare(strict_types=1);

namespace Src\Inventory\Restock\Domain\ValueObjects;

/** How urgent it is to buy more of a product, from the units left and how fast they sell. */
enum RestockStatus: string
{
    case OutOfStock = 'agotado';
    case Critical = 'critico';
    case Low = 'bajo';
    case Ok = 'ok';

    /** Position in the list: the most urgent first. */
    public function rank(): int
    {
        return match ($this) {
            self::OutOfStock => 0,
            self::Critical => 1,
            self::Low => 2,
            self::Ok => 3,
        };
    }
}
