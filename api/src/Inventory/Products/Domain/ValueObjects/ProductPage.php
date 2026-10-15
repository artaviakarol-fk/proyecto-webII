<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Domain\ValueObjects;

use Src\Inventory\Products\Domain\Entities\Product;

final readonly class ProductPage
{
    /**
     * Hold one page of the catalog and how many products match in total.
     *
     * @param  list<Product>  $products
     */
    public function __construct(
        public array $products,
        public int $page,
        public int $perPage,
        public int $total,
    ) {}

    /** Number of the last page, at least 1. */
    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
