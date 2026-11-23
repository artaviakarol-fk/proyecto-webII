<?php

declare(strict_types=1);

namespace Src\Inventory\Reservations\Application\Contracts;

use Src\Inventory\Reservations\Application\DTOs\ReservationDetails;

interface ReservationFeed
{
    /** Show a new reserva on the screens of the staff of its negocio. */
    public function reservationHeld(string $negocioId, ReservationDetails $details): void;

    /** Tell the screens of the staff of the negocio that a reserva changed its state (for example, it was cancelled). */
    public function reservationChanged(string $negocioId, string $reservationId, string $status): void;
}
