<?php

declare(strict_types=1);

namespace Src\Sales\Purchases\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class NoReservationsToBuyException extends DomainException
{
    /** Build the exception for a purchase of a cliente that holds no reserva: it never reserved, or its reservas expired. */
    public static function create(): self
    {
        return new self('sales.purchase_without_reservations');
    }
}
