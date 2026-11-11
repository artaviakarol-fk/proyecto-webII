<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Domain\ValueObjects;

use Src\Shared\Domain\ValueObjects\Money;

/** The charge a sale on fiado left on a customer: who owes it and how much. */
final readonly class FiadoCharge
{
    /** Hold the customer and the amount charged by the sale. */
    public function __construct(
        public CustomerId $customerId,
        public string $saleId,
        public Money $amount,
    ) {}
}
