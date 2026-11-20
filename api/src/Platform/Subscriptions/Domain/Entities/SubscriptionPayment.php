<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Domain\Entities;

use DateTimeImmutable;
use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionPaymentMethod;
use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionPaymentStatus;
use Src\Shared\Domain\ValueObjects\Money;
use Src\Shared\Domain\ValueObjects\NegocioId;

/**
 * A payment of some months of the platform. A manual one is approved when it is registered; one through a provider
 * stays pending until the provider says it was paid. It extends the rent only once, when it becomes approved.
 */
final class SubscriptionPayment
{
    public const string MANUAL = 'manual';

    /**
     * @param  array<string, mixed>  $providerAnswer
     */
    private function __construct(
        public readonly string $id,
        public readonly NegocioId $negocioId,
        public readonly Money $amount,
        public readonly int $months,
        public readonly SubscriptionPaymentMethod $method,
        public readonly string $provider,
        public private(set) ?string $reference,
        public private(set) SubscriptionPaymentStatus $status,
        public readonly ?string $registeredBy,
        public private(set) array $providerAnswer,
        public private(set) ?DateTimeImmutable $appliedAt,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    /** A payment that a superadmin received outside of the system, approved from the start. */
    public static function manual(
        string $id,
        NegocioId $negocioId,
        Money $amount,
        int $months,
        SubscriptionPaymentMethod $method,
        ?string $reference,
        string $registeredBy,
        DateTimeImmutable $now,
    ): self {
        return new self($id, $negocioId, $amount, $months, $method, self::MANUAL, $reference, SubscriptionPaymentStatus::Approved, $registeredBy, [], null, $now);
    }

    /** A payment that the administrator starts with a provider; it waits for the provider. */
    public static function throughProvider(
        string $id,
        NegocioId $negocioId,
        Money $amount,
        int $months,
        SubscriptionPaymentMethod $method,
        string $provider,
        string $requestedBy,
        DateTimeImmutable $now,
    ): self {
        return new self($id, $negocioId, $amount, $months, $method, $provider, null, SubscriptionPaymentStatus::Pending, $requestedBy, [], null, $now);
    }

    /**
     * Rebuild a payment from its stored state.
     *
     * @param  array<string, mixed>  $providerAnswer
     */
    public static function reconstitute(
        string $id,
        NegocioId $negocioId,
        Money $amount,
        int $months,
        SubscriptionPaymentMethod $method,
        string $provider,
        ?string $reference,
        SubscriptionPaymentStatus $status,
        ?string $registeredBy,
        array $providerAnswer,
        ?DateTimeImmutable $appliedAt,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $negocioId, $amount, $months, $method, $provider, $reference, $status, $registeredBy, $providerAnswer, $appliedAt, $createdAt);
    }

    /**
     * Take what the provider says about the charge. Once approved or rejected, the payment does not change again.
     *
     * @param  array<string, mixed>  $answer
     */
    public function settle(SubscriptionPaymentStatus $status, ?string $reference, array $answer): void
    {
        if ($this->status !== SubscriptionPaymentStatus::Pending) {
            return;
        }

        $this->status = $status;
        $this->reference = $reference ?? $this->reference;
        $this->providerAnswer = $answer;
    }

    /** Whether the payment must still extend the rent: it was approved and it did not extend it yet. */
    public function mustApply(): bool
    {
        return $this->status === SubscriptionPaymentStatus::Approved && $this->appliedAt === null;
    }

    /** Mark that the months of the payment were added to the rent, so they are never added twice. */
    public function markApplied(DateTimeImmutable $now): void
    {
        $this->appliedAt = $now;
    }
}
