<?php

declare(strict_types=1);

namespace Src\Inventory\Reservations\Application\DTOs;

use DateTimeImmutable;

final readonly class ReserveCommand
{
    /** Carry the product, the units the cliente wants to hold and the current time. */
    public function __construct(
        public string $productId,
        public int $quantity,
        public DateTimeImmutable $now,
    ) {}
}
