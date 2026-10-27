<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class PaymentProviderMismatchException extends DomainException
{
    /** A payment can only be refunded by the provider that charged it, not by the one configured now. */
    public static function create(string $charged, string $configured): self
    {
        return new self('payments.provider_mismatch', ['charged' => $charged, 'configured' => $configured]);
    }
}
