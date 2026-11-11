<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\DTOs;

use Src\Shared\Domain\ValueObjects\Money;

final readonly class CreditableSale
{
    /** Carry the total of a sale and whether it is still open. */
    public function __construct(
        public string $id,
        public Money $total,
        public bool $open,
    ) {}
}
