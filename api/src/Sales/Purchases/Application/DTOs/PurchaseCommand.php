<?php

declare(strict_types=1);

namespace Src\Sales\Purchases\Application\DTOs;

use DateTimeImmutable;

/** What the cliente sends to buy what it reserved: the way of paying, the gateway and, for liquor, its id number. */
final readonly class PurchaseCommand
{
    /** Hold plain data; the use cases validate it. */
    public function __construct(
        public string $method,
        public string $gateway,
        public string $providerMethodId,
        public ?string $buyerIdNumber,
        public DateTimeImmutable $now,
    ) {}
}
