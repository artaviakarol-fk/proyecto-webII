<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\Entities;

use DateTimeImmutable;
use Src\Billing\Payments\Domain\Exceptions\PaymentAlreadySettledException;
use Src\Billing\Payments\Domain\Exceptions\PaymentNotRefundableException;
use Src\Billing\Payments\Domain\Exceptions\RefundRejectedException;
use Src\Billing\Payments\Domain\ValueObjects\ChargeReference;
use Src\Billing\Payments\Domain\ValueObjects\ChargeResult;
use Src\Billing\Payments\Domain\ValueObjects\PaymentId;
use Src\Billing\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Billing\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Billing\Payments\Domain\ValueObjects\RefundResult;
use Src\Billing\Payments\Domain\ValueObjects\RefundStatus;
use Src\Billing\Payments\Domain\ValueObjects\SaleId;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\Money;
use Src\Shared\Domain\ValueObjects\NegocioId;

final class Payment
{
    /**
     * Hold an attempt to pay a sale with a provider: how much, the way of paying, the answer of the provider and the stage it is in.
     *
     * @param  array<string, mixed>  $payload  The last answer of the provider, kept as it came.
     */
    private function __construct(
        public readonly PaymentId $id,
        public readonly NegocioId $negocioId,
        public readonly SaleId $saleId,
        public readonly string $provider,
        public readonly PaymentMethod $method,
        public readonly Money $amount,
        public private(set) PaymentStatus $status,
        public private(set) ?ChargeReference $reference,
        public private(set) ?string $declineCode,
        public private(set) array $payload,
        public readonly DateTimeImmutable $createdAt,
        public private(set) ?DateTimeImmutable $settledAt,
        public private(set) ?string $refundReference = null,
    ) {}

    /** Start a payment: it is pending until the provider gives a final answer. */
    public static function start(PaymentId $id, NegocioId $negocioId, SaleId $saleId, string $provider, PaymentMethod $method, Money $amount, DateTimeImmutable $now): self
    {
        if ($amount->isZero()) {
            throw new InvalidValueException('payments.amount_must_be_positive');
        }

        return new self($id, $negocioId, $saleId, $provider, $method, $amount, PaymentStatus::Pending, null, null, [], $now, null);
    }

    /**
     * Rebuild a payment from its stored state.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function reconstitute(
        PaymentId $id,
        NegocioId $negocioId,
        SaleId $saleId,
        string $provider,
        PaymentMethod $method,
        Money $amount,
        PaymentStatus $status,
        ?ChargeReference $reference,
        ?string $declineCode,
        array $payload,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $settledAt,
        ?string $refundReference = null,
    ): self {
        return new self($id, $negocioId, $saleId, $provider, $method, $amount, $status, $reference, $declineCode, $payload, $createdAt, $settledAt, $refundReference);
    }

    /**
     * Take the answer of the provider. A payment that already got a final answer keeps it:
     * the same answer again changes nothing and a different one is an error.
     */
    public function apply(ChargeResult $result, DateTimeImmutable $now): void
    {
        if ($this->status->isSettled()) {
            if ($result->status !== $this->status) {
                throw PaymentAlreadySettledException::create();
            }

            return;
        }

        $this->reference = $result->reference;
        $this->status = $result->status;
        $this->declineCode = $result->status === PaymentStatus::Failed ? $result->declineCode : null;
        $this->payload = $result->payload;
        $this->settledAt = $result->status->isSettled() ? $now : null;
    }

    /** Tell whether the money of this payment can be given back: only a paid one, with the reference of its charge. */
    public function isRefundable(): bool
    {
        return $this->status === PaymentStatus::Paid && $this->reference !== null;
    }

    /**
     * Take the answer of the provider to a refund of the whole amount. A refund in course or done leaves the payment
     * refunded, with the reference of the refund and its answer kept next to the one of the charge; a refused one is an
     * error and the payment stays paid.
     */
    public function refund(RefundResult $result): void
    {
        if (! $this->isRefundable()) {
            throw PaymentNotRefundableException::create();
        }

        if ($result->status === RefundStatus::Failed) {
            throw RefundRejectedException::create($result->failureReason);
        }

        $this->status = PaymentStatus::Refunded;
        $this->refundReference = $result->reference;
        $this->payload = [...$this->payload, 'refund' => [...$result->payload, 'status' => $result->status->value]];
    }
}
