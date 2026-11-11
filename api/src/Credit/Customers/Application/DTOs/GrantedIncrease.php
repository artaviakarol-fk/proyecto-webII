<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\DTOs;

use Src\Credit\Customers\Domain\Entities\Customer;

final readonly class GrantedIncrease
{
    /** Carry the increase as it was recorded and the customer with the limit it now has. */
    public function __construct(
        public CreditIncreaseSummary $increase,
        public Customer $customer,
    ) {}
}
