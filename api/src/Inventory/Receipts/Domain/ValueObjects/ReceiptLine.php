<?php

declare(strict_types=1);

namespace Src\Inventory\Receipts\Domain\ValueObjects;

use Src\Inventory\Shared\Domain\ValueObjects\Money;
use Src\Inventory\Shared\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class ReceiptLine
{
    public const int MAX_QUANTITY = 1_000_000;

    /** One product that arrived: how many units, what each one cost and whether it entered the catalog with it. */
    public function __construct(
        public ProductId $productId,
        public int $quantity,
        public Money $unitCost,
        public bool $newProduct = false,
    ) {
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw new InvalidValueException('inventory.quantity_out_of_range', ['max' => self::MAX_QUANTITY]);
        }
    }
}
