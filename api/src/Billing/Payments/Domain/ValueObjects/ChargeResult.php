<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\ValueObjects;

/** What the provider answered about a charge, already translated to the terms of the payments. */
final readonly class ChargeResult
{
    /**
     * The payload is the answer of the provider, kept as it came, without card data.
     *
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public PaymentStatus $status,
        public ChargeReference $reference,
        public ?string $declineCode = null,
        public array $payload = [],
    ) {}
}
