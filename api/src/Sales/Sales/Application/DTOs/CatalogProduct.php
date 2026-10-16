<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Application\DTOs;

final readonly class CatalogProduct
{
    /** A product of the catalog as the sale sees it: its name, the price it sells for as a decimal string and whether it is liquor. */
    public function __construct(
        public string $id,
        public string $name,
        public string $salePrice,
        public bool $liquor,
    ) {}
}
