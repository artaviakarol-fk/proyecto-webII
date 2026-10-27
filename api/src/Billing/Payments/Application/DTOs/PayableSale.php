<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Application\DTOs;

use Src\Billing\Payments\Domain\ValueObjects\SaleId;
use Src\Shared\Domain\ValueObjects\Money;

/** What a payment needs to know about a sale: how much it costs and whether it can still be paid. */
final readonly class PayableSale
{
    /** The sale can be paid only while it is open. */
    public function __construct(
        public SaleId $id,
        public Money $total,
        public bool $open,
    ) {}
}
