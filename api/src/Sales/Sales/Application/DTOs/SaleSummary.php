<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Application\DTOs;

final readonly class SaleSummary
{
    /** A sale as it appears in the listing, without its lines: the total is a decimal string in colones. */
    public function __construct(
        public string $id,
        public string $status,
        public string $paymentMethod,
        public string $cashierId,
        public string $cashierName,
        public int $lineCount,
        public string $total,
        public string $createdAt,
        public ?string $paidAt,
        public ?string $cancelledAt,
    ) {}
}
