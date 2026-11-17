<?php

declare(strict_types=1);

namespace Src\Inventory\Reservations\Domain\ValueObjects;

enum ReservationStatus: string
{
    /** The units are held for the cliente until the reserva expires. */
    case Active = 'activa';

    /** The cliente let the units go, or replaced the reserva with a new one. */
    case Cancelled = 'cancelada';

    /** The cliente paid: the units became a sale. */
    case Confirmed = 'confirmada';
}
