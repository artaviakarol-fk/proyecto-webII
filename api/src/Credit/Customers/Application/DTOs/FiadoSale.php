<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\DTOs;

use Src\Credit\Customers\Domain\Entities\Customer;
use Src\Shared\Domain\ValueObjects\Money;

final readonly class FiadoSale
{
    /** Carry the sale that went on fiado, its total and the customer as they stand after the charge. */
    public function __construct(
        public string $saleId,
        public Money $total,
        public Customer $customer,
    ) {}
}
