<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class RefundRejectedException extends DomainException
{
    /** The provider refused to give the money back; the reason says why. */
    public static function create(?string $reason): self
    {
        return new self('payments.refund_rejected', ['reason' => $reason ?? 'desconocido']);
    }
}
