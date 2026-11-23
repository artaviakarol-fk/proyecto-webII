<?php

declare(strict_types=1);

namespace Src\Inventory\Reservations\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Inventory\Reservations\Application\Contracts\ReservationReader;
use Src\Inventory\Reservations\Application\Contracts\ReservationFeed;
use Src\Inventory\Reservations\Domain\Contracts\ReservationRepository;
use Src\Inventory\Reservations\Infrastructure\Broadcasting\ReverbReservationFeed;
use Src\Inventory\Reservations\Infrastructure\Persistence\DatabaseReservations;

final class ReservationsServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        ReservationRepository::class => DatabaseReservations::class,
        ReservationReader::class => DatabaseReservations::class,
        ReservationFeed::class => ReverbReservationFeed::class,
    ];
}
