<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\DTOs;

/** One increase as the administration reviews it, with the names of the customer and of the cashier. */
final readonly class CreditIncreaseSummary
{
    /** Carry the increase; amounts are decimal strings and moments are ISO 8601 in Costa Rica time. */
    public function __construct(
        public string $id,
        public string $customerId,
        public string $customerName,
        public string $amount,
        public string $reason,
        public string $grantedById,
        public string $grantedByName,
        public string $grantedAt,
        public string $validUntil,
        public ?string $revertedAt,
        public ?string $revertedById,
        public ?string $revertedByName,
        public bool $active,
    ) {}
}
