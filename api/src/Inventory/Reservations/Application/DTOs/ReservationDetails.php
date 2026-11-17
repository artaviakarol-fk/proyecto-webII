<?php

declare(strict_types=1);

namespace Src\Inventory\Reservations\Application\DTOs;

use DateTimeImmutable;
use Src\Shared\Domain\ValueObjects\Money;

final readonly class ReservationDetails
{
    /** Carry an active reserva with the name and the price of its product, as the cliente sees it. */
    public function __construct(
        public string $id,
        public string $productId,
        public string $productName,
        public int $quantity,
        public Money $unitPrice,
        public DateTimeImmutable $expiresAt,
    ) {}

    /** What the units of the reserva cost. */
    public function subtotal(): Money
    {
        return $this->unitPrice->times($this->quantity);
    }
}
