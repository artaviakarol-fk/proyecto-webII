<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Application\DTOs;

final readonly class ProductData
{
    /** Carry the editable data of a product as the request sends it; amounts are decimal strings. */
    public function __construct(
        public string $code,
        public string $name,
        public ?string $category,
        public string $salePrice,
        public string $currentCost,
        public int $stock,
        public bool $perishable,
        public bool $liquor,
    ) {}
}
