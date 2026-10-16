<?php

declare(strict_types=1);

namespace Src\Inventory\Expirations\Application\DTOs;

final readonly class MarkedUnits
{
    /** The units just marked as expired and how the stock of their product stands afterwards. */
    public function __construct(
        public string $id,
        public string $productId,
        public int $quantity,
        public string $reason,
        public string $status,
        public string $markedBy,
        public string $markedAt,
        public int $stock,
        public int $expiredUnits,
        public int $sellableStock,
    ) {}
}
