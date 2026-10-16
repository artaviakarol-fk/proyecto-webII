<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Application\DTOs;

final readonly class SaleLineData
{
    /** One line as the request sends it: the product and how many units; the price comes from the catalog. */
    public function __construct(
        public string $productId,
        public int $quantity,
    ) {}
}
