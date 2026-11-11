<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\DTOs;

/** What the receipt of an abono shows: who paid, how much, what they owed before and what remains. */
final readonly class CreditPaymentReceipt
{
    /** Carry the abono; amounts are decimal strings and the moment is ISO 8601 in Costa Rica time. */
    public function __construct(
        public string $id,
        public string $negocioName,
        public string $customerId,
        public string $customerName,
        public string $amount,
        public string $method,
        public string $balanceBefore,
        public string $balanceAfter,
        public string $receivedById,
        public string $receivedByName,
        public string $receivedAt,
    ) {}
}
