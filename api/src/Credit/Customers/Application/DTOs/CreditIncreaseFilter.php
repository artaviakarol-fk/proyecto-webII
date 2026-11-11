<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\DTOs;

use DateTimeImmutable;

final readonly class CreditIncreaseFilter
{
    /** Optionally only the increases of one customer, and optionally only those that count at a moment. */
    public function __construct(
        public ?string $customerId,
        public bool $onlyActive,
        public DateTimeImmutable $at,
    ) {}
}
