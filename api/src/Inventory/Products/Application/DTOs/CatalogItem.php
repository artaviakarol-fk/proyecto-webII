<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Application\DTOs;

use Src\Inventory\Products\Domain\Entities\Product;
use Src\Inventory\Products\Domain\ValueObjects\Availability;

final readonly class CatalogItem
{
    /** Carry a product of the catalog, the units of it that can be sold and the units clientes hold right now. */
    public function __construct(
        public Product $product,
        public Availability $availability,
        public int $reservedUnits = 0,
    ) {}

    /** Units a cliente can still take: the sellable ones minus the ones held by reservas; never negative. */
    public function available(): int
    {
        return max(0, $this->availability->sellable - $this->reservedUnits);
    }

    /** Tell whether no unit is left for a cliente. */
    public function isSoldOut(): bool
    {
        return $this->available() === 0;
    }
}
