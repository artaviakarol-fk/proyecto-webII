<?php

declare(strict_types=1);

namespace Src\Sales\Purchases\Infrastructure\Inventory;

use DateTimeImmutable;
use Src\Inventory\Reservations\Domain\Contracts\ReservationRepository;
use Src\Sales\Purchases\Application\Contracts\CartReservations;
use Src\Sales\Sales\Application\DTOs\SaleLineData;
use Src\Shared\Domain\Contracts\CurrentUser;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class InventoryCartReservations implements CartReservations
{
    /** Receive the negocio, the cliente and the reservas of the inventory. */
    public function __construct(
        private NegocioContext $context,
        private CurrentUser $user,
        private ReservationRepository $reservations,
    ) {}

    /**
     * Take the reservas of the cliente through the inventory and answer them as lines of a sale.
     *
     * @return list<SaleLineData>
     */
    public function claim(DateTimeImmutable $now): array
    {
        $lines = [];

        foreach ($this->reservations->claimActive($this->context->current(), $this->user->id(), $now) as $productId => $quantity) {
            $lines[] = new SaleLineData($productId, $quantity);
        }

        return $lines;
    }
}
