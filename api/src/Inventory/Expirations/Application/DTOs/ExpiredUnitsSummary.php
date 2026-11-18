<?php

declare(strict_types=1);

namespace Src\Inventory\Expirations\Application\DTOs;

final readonly class ExpiredUnitsSummary
{
    /** Units found expired that are still on the shelf, with the product they belong to and who marked them. */
    public function __construct(
        public string $id,
        public string $productId,
        public string $productCode,
        public string $productName,
        public int $quantity,
        public string $reason,
        public string $markedBy,
        public string $markedAt,
    ) {}
}
