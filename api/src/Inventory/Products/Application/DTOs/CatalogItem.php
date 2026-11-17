<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Application\DTOs;

use Src\Inventory\Products\Domain\Entities\Product;
use Src\Inventory\Products\Domain\ValueObjects\Availability;

final readonly class CatalogItem
{
    /** Carry a product of the catalog together with the units of it that can really be sold. */
    public function __construct(
        public Product $product,
        public Availability $availability,
    ) {}
}
