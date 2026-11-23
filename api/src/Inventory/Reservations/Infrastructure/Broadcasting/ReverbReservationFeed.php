<?php

declare(strict_types=1);

namespace Src\Inventory\Reservations\Infrastructure\Broadcasting;

use Src\Inventory\Reservations\Application\Contracts\ReservationFeed;
use Src\Inventory\Reservations\Application\DTOs\ReservationDetails;

final readonly class ReverbReservationFeed implements ReservationFeed
{
    /** Broadcast the new reserva from the queue, once the transaction is committed. */
    public function reservationHeld(string $negocioId, ReservationDetails $details): void
    {
        ReservaCreada::dispatch(
            $negocioId,
            $details->id,
            $details->productName,
            $details->quantity,
            $details->expiresAt->format(DATE_ATOM),
        );
    }

    /** Broadcast the new state of a reserva to the staff of the negocio. */
    public function reservationChanged(string $negocioId, string $reservationId, string $status): void
    {
        ReservaEstado::dispatch($negocioId, $reservationId, $status);
    }
}
