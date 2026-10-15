<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Domain\ValueObjects;

/** How many units of a product can really be sold: the stock minus the units marked as expired. */
final readonly class Availability
{
    public int $sellable;

    /** Compute the sellable units; expired units never make it negative. */
    public function __construct(
        public Stock $stock,
        public int $expiredUnits,
    ) {
        $this->sellable = max(0, $stock->units - $expiredUnits);
    }

    /** Tell whether no unit can be sold. */
    public function isSoldOut(): bool
    {
        return $this->sellable === 0;
    }
}
