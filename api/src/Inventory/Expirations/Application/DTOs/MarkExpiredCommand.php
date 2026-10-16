<?php

declare(strict_types=1);

namespace Src\Inventory\Expirations\Application\DTOs;

use DateTimeImmutable;

final readonly class MarkExpiredCommand
{
    /** Carry the product, how many units are expired and why, as the request sends them, and the moment of the request. */
    public function __construct(
        public string $productId,
        public int $quantity,
        public string $reason,
        public DateTimeImmutable $now,
    ) {}
}
