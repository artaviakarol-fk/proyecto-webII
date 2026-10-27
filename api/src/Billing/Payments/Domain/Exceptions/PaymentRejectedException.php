<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class PaymentRejectedException extends DomainException
{
    /** The provider or the bank refused the payment; the code says why. */
    public static function create(?string $declineCode): self
    {
        return new self('payments.payment_rejected', ['code' => $declineCode ?? 'desconocido']);
    }
}
