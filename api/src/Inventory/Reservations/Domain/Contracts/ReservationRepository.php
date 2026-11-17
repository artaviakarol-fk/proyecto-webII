<?php

declare(strict_types=1);

namespace Src\Inventory\Reservations\Domain\Contracts;

use DateTimeImmutable;
use Src\Inventory\Reservations\Domain\Entities\Reservation;
use Src\Inventory\Reservations\Domain\Exceptions\ReservationExceedsAvailableException;
use Src\Inventory\Reservations\Domain\ValueObjects\ReservationId;
use Src\Shared\Domain\ValueObjects\NegocioId;

interface ReservationRepository
{
    /** Generate the identity for a new reserva. */
    public function nextId(): ReservationId;

    /**
     * Store the reserva after locking the product row, so two clientes cannot hold the same units: the product
     * never has more units held than units free. A previous active reserva of the same cliente for the same
     * product is replaced by this one.
     *
     * @throws ReservationExceedsAvailableException
     */
    public function save(Reservation $reservation): void;

    /** Let go an active reserva of the cliente; answer false when there is none with that identifier. */
    public function cancel(NegocioId $negocioId, string $userId, ReservationId $id, DateTimeImmutable $now): bool;

    /**
     * Take all the active, not expired reservas of the cliente to buy them: they pass to confirmed, so they stop holding
     * units, and the units each product was held for are answered. The rows are locked, so two purchases of the same
     * cliente at the same time cannot take the same reserva twice.
     *
     * @return array<string, int> Units held, by product.
     */
    public function claimActive(NegocioId $negocioId, string $userId, DateTimeImmutable $now): array;
}
