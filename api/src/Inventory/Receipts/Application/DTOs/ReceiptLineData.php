<?php

declare(strict_types=1);

namespace Src\Inventory\Receipts\Application\DTOs;

use Src\Inventory\Products\Application\DTOs\ProductData;

final readonly class ReceiptLineData
{
    /** One line as the request sends it: an existing product, or the data of a new one, with quantity and unit cost. */
    public function __construct(
        public ?string $productId,
        public ?ProductData $newProduct,
        public int $quantity,
        public string $unitCost,
    ) {}
}
