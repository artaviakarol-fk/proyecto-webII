<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Domain\ValueObjects;

use Src\Sales\Shared\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\Money;

final readonly class SaleLine
{
    public const int MAX_QUANTITY = 10_000;

    /** One product of the sale: how many units, the price of each one when it was sold and whether it is liquor. */
    public function __construct(
        public ProductId $productId,
        public int $quantity,
        public Money $unitPrice,
        public bool $liquor = false,
    ) {
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw new InvalidValueException('sales.quantity_out_of_range', ['max' => self::MAX_QUANTITY]);
        }
    }

    /** What the line adds to the total of the sale. */
    public function subtotal(): Money
    {
        return $this->unitPrice->times($this->quantity);
    }
}
