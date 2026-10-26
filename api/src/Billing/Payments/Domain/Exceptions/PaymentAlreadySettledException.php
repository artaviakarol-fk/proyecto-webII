<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class PaymentAlreadySettledException extends DomainException
{
    /** A payment that got a final answer cannot get a different one. */
    public static function create(): self
    {
        return new self('payments.payment_already_settled');
    }
}
