<?php

declare(strict_types=1);

namespace Src\Inventory\Reservations\Application\UseCases;

use DateTimeImmutable;
use Src\Inventory\Reservations\Domain\Contracts\ReservationRepository;
use Src\Inventory\Reservations\Domain\Exceptions\ReservationNotFoundException;
use Src\Inventory\Reservations\Domain\ValueObjects\ReservationId;
use Src\Shared\Domain\Contracts\CurrentUser;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class CancelReservation
{
    /** Receive the ports of the negocio and the account, and the reservas. */
    public function __construct(
        private NegocioContext $context,
        private CurrentUser $user,
        private ReservationRepository $reservations,
    ) {}

    /** Let go a reserva of the cliente of the request, so its units are free again; one of somebody else is missing. */
    public function execute(string $reservationId, DateTimeImmutable $now): void
    {
        $cancelled = $this->reservations->cancel($this->context->current(), $this->user->id(), new ReservationId($reservationId), $now);

        if (! $cancelled) {
            throw ReservationNotFoundException::create();
        }
    }
}
