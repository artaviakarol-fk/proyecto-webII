<?php

declare(strict_types=1);

namespace Src\Inventory\Reservations\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class ReservationExceedsAvailableException extends DomainException
{
    /** Build the exception for a reserva of more units than the product has free right now. */
    public static function create(string $product, int $quantity, int $available): self
    {
        return new self('inventory.reservation_exceeds_available', ['product' => $product, 'quantity' => $quantity, 'available' => $available]);
    }
}
