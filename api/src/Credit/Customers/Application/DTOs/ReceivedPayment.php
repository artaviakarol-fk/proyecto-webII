<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\DTOs;

use Src\Credit\Customers\Domain\Entities\Customer;

final readonly class ReceivedPayment
{
    /** Carry the receipt of the abono and the customer with what they owe after it. */
    public function __construct(
        public CreditPaymentReceipt $receipt,
        public Customer $customer,
    ) {}
}
