<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

/** The editable data of a product, validated together because the sale price may not be zero. */
final readonly class ProductDetails
{
    /** Hold the details and reject a sale price of zero. */
    public function __construct(
        public ProductCode $code,
        public ProductName $name,
        public ?Category $category,
        public Money $salePrice,
        public Money $currentCost,
        public Stock $stock,
        public bool $perishable,
        public bool $liquor,
    ) {
        if ($salePrice->isZero()) {
            throw new InvalidValueException('inventory.price_must_be_positive');
        }
    }
}
