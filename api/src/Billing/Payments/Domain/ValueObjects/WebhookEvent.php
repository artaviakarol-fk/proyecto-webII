<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\ValueObjects;

/** What a notification of the provider says, already translated: which event it is and which charge it talks about. */
final readonly class WebhookEvent
{
    /** The event id is the key that makes a repeated notification recognisable. */
    public function __construct(
        public string $id,
        public string $type,
        public ChargeReference $reference,
    ) {}
}
