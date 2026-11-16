<?php

declare(strict_types=1);

namespace Src\Sales\SaleDrafts\Application\DTOs;

use Src\Shared\Domain\ValueObjects\Money;

final readonly class DraftLine
{
    /** A line of the draft: the product of the catalog it matched, what the cashier wrote and whether the stock is enough. */
    public function __construct(
        public DraftProduct $product,
        public string $written,
        public int $quantity,
        public Money $subtotal,
        public bool $enoughStock,
    ) {}
}
