<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Domain\Contracts;

use Src\Credit\Customers\Domain\Entities\CreditPayment;
use Src\Credit\Customers\Domain\ValueObjects\CreditPaymentId;

interface CreditPaymentRepository
{
    /** Generate the identity for a new abono. */
    public function nextId(): CreditPaymentId;

    /** Store a new abono; an abono is never changed. */
    public function add(CreditPayment $payment): void;
}
