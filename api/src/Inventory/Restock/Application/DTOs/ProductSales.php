<?php

declare(strict_types=1);

namespace Src\Inventory\Restock\Application\DTOs;

/** A product of the negocio with the units it has and the units it sold in the period. */
final readonly class ProductSales
{
    /** Receive the product and its figures. */
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public ?string $category,
        public int $stock,
        public int $sold,
    ) {}
}
