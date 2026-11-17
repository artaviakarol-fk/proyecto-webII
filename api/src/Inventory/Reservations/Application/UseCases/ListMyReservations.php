<?php

declare(strict_types=1);

namespace Src\Inventory\Reservations\Application\UseCases;

use DateTimeImmutable;
use Src\Inventory\Reservations\Application\Contracts\ReservationReader;
use Src\Inventory\Reservations\Application\DTOs\ReservationDetails;
use Src\Shared\Domain\Contracts\CurrentUser;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class ListMyReservations
{
    /** Receive the ports of the negocio and the account, and the reader of the reservas. */
    public function __construct(
        private NegocioContext $context,
        private CurrentUser $user,
        private ReservationReader $reader,
    ) {}

    /**
     * The reservas the cliente of the request still holds; the expired ones are gone.
     *
     * @return list<ReservationDetails>
     */
    public function execute(DateTimeImmutable $now): array
    {
        return $this->reader->activeOf($this->context->current(), $this->user->id(), $now);
    }
}
