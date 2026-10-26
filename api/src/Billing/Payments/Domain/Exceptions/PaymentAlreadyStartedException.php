<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class PaymentAlreadyStartedException extends DomainException
{
    /** The sale already has a pending or paid payment. */
    public static function create(): self
    {
        return new self('payments.payment_already_started');
    }
}
