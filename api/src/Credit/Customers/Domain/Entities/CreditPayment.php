<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Domain\Entities;

use DateTimeImmutable;
use Src\Credit\Customers\Domain\ValueObjects\CreditPaymentId;
use Src\Credit\Customers\Domain\ValueObjects\CustomerId;
use Src\Shared\Domain\ValueObjects\Money;
use Src\Shared\Domain\ValueObjects\NegocioId;

/**
 * US-305: an abono, a payment a customer makes on what they owe on fiado. It keeps the balance
 * before and after it, so its receipt is always the same, and it never changes once recorded.
 */
final readonly class CreditPayment
{
    /** The only way an abono is received for now; the payment through a provider comes later. */
    public const string CASH = 'efectivo';

    /** Hold the abono. */
    private function __construct(
        public CreditPaymentId $id,
        public NegocioId $negocioId,
        public CustomerId $customerId,
        public Money $amount,
        public string $method,
        public Money $balanceBefore,
        public Money $balanceAfter,
        public string $receivedBy,
        public DateTimeImmutable $receivedAt,
    ) {}

    /** Record an abono: what remains is what was owed minus the abono. Only a customer creates it, after its checks. */
    public static function receive(
        CreditPaymentId $id,
        NegocioId $negocioId,
        CustomerId $customerId,
        Money $amount,
        string $method,
        Money $balanceBefore,
        string $receivedBy,
        DateTimeImmutable $at,
    ): self {
        return new self($id, $negocioId, $customerId, $amount, $method, $balanceBefore, new Money($balanceBefore->cents - $amount->cents), $receivedBy, $at);
    }
}
