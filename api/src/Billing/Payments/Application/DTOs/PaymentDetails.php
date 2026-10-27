<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Application\DTOs;

use DateTimeImmutable;
use Src\Billing\Payments\Domain\Entities\Payment;

/** A payment as it is shown: the amount is a decimal string in colones. */
final readonly class PaymentDetails
{
    /** Hold the data of a payment without the entity behind it. */
    public function __construct(
        public string $id,
        public string $saleId,
        public string $provider,
        public string $method,
        public string $amount,
        public string $currency,
        public string $status,
        public ?string $reference,
        public ?string $declineCode,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $settledAt,
        public ?string $approvalUrl = null,
        public ?string $refundReference = null,
    ) {}

    /** Show a payment. */
    public static function of(Payment $payment): self
    {
        return new self(
            $payment->id->value,
            $payment->saleId->value,
            $payment->provider,
            $payment->method->value,
            $payment->amount->toDecimal(),
            'CRC',
            $payment->status->value,
            $payment->reference?->value,
            $payment->declineCode,
            $payment->createdAt,
            $payment->settledAt,
            is_string($payment->payload['approval_url'] ?? null) ? $payment->payload['approval_url'] : null,
            $payment->refundReference,
        );
    }
}
