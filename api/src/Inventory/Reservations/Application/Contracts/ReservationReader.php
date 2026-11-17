<?php

declare(strict_types=1);

namespace Src\Inventory\Reservations\Application\Contracts;

use DateTimeImmutable;
use Src\Inventory\Reservations\Application\DTOs\ReservationDetails;
use Src\Shared\Domain\ValueObjects\NegocioId;

interface ReservationReader
{
    /**
     * The reservas of a cliente that are active and have not expired, oldest first.
     *
     * @return list<ReservationDetails>
     */
    public function activeOf(NegocioId $negocioId, string $userId, DateTimeImmutable $now): array;
}
