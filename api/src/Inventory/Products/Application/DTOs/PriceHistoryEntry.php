<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Application\DTOs;

final readonly class PriceHistoryEntry
{
    /** One row of the price history: amounts are decimal strings in colones. */
    public function __construct(
        public string $recordedAt,
        public string $cost,
        public string $salePrice,
        public string $reason,
        public string $userId,
        public string $userName,
        public ?string $referenceId,
    ) {}
}
