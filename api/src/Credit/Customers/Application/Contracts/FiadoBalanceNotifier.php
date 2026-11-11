<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\Contracts;

use Src\Credit\Customers\Domain\Entities\Customer;

interface FiadoBalanceNotifier
{
    /**
     * Announce that what a customer owes changed (SaldoFiadoActualizado). It is announced only if the
     * transaction commits; the WhatsApp notice of US-505 will listen to it.
     */
    public function balanceChanged(Customer $customer): void;
}
