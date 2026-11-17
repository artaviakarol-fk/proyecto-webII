<?php

declare(strict_types=1);

namespace Src\Inventory\Reservations\Application\UseCases;

use Src\Inventory\Reservations\Application\Contracts\ReservationReader;
use Src\Inventory\Reservations\Application\DTOs\ReservationDetails;
use Src\Inventory\Reservations\Application\DTOs\ReserveCommand;
use Src\Inventory\Reservations\Domain\Contracts\ReservationRepository;
use Src\Inventory\Reservations\Domain\Entities\Reservation;
use Src\Inventory\Reservations\Domain\Exceptions\ReservationNotFoundException;
use Src\Inventory\Shared\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\Contracts\CurrentUser;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class ReserveProduct
{
    /** Receive the ports of the negocio, the account, the transaction and the reservas. */
    public function __construct(
        private NegocioContext $context,
        private CurrentUser $user,
        private TransactionRunner $transaction,
        private ReservationRepository $reservations,
        private ReservationReader $reader,
    ) {}

    /**
     * Hold units of a product of the current negocio for the cliente of the request. The units stay in the stock,
     * but nobody else can reserve or buy them until the reserva expires, is cancelled or is paid.
     */
    public function execute(ReserveCommand $command): ReservationDetails
    {
        $reservation = Reservation::hold(
            $this->reservations->nextId(),
            $this->context->current(),
            new ProductId($command->productId),
            $this->user->id(),
            $command->quantity,
            $command->now,
        );

        $this->transaction->run(fn () => $this->reservations->save($reservation));

        return array_find(
            $this->reader->activeOf($reservation->negocioId, $reservation->userId, $command->now),
            fn (ReservationDetails $details): bool => $details->id === $reservation->id->value,
        ) ?? throw ReservationNotFoundException::create();
    }
}
