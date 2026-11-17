<?php

declare(strict_types=1);

namespace Src\Inventory\Reservations\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class ReservationNotFoundException extends NotFoundException
{
    /** Build the exception for a reserva that is not an active one of the cliente: missing, expired or of somebody else. */
    public static function create(): self
    {
        return new self('inventory.reservation_not_found');
    }
}
