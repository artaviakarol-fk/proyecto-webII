<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\ValueObjects;

/** What the provider answered about a refund, already translated to the terms of the payments. */
final readonly class RefundResult
{
    /**
     * The reference is the id of the refund at the provider; the payload is its answer, kept as it came.
     *
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public RefundStatus $status,
        public string $reference,
        public ?string $failureReason = null,
        public array $payload = [],
    ) {}
}
