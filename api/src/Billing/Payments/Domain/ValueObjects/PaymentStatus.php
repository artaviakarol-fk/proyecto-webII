<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\ValueObjects;

enum PaymentStatus: string
{
    case Pending = 'pendiente';
    case Paid = 'pagado';
    case Failed = 'fallido';
    case Refunded = 'reembolsado';

    /**
     * Tell whether the payment still counts for its sale: a pending or a paid one blocks a second attempt; a failed or
     * a refunded one leaves room for another.
     */
    public function isLive(): bool
    {
        return $this === self::Pending || $this === self::Paid;
    }

    /** Tell whether the provider already gave a final answer. */
    public function isSettled(): bool
    {
        return $this !== self::Pending;
    }
}
