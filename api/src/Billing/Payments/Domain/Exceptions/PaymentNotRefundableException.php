<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class PaymentNotRefundableException extends DomainException
{
    /** Only a paid payment can be refunded, and only once. */
    public static function create(): self
    {
        return new self('payments.payment_not_refundable');
    }
}
