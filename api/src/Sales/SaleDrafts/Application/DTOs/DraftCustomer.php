<?php

declare(strict_types=1);

namespace Src\Sales\SaleDrafts\Application\DTOs;

use Src\Shared\Domain\ValueObjects\Money;

final readonly class DraftCustomer
{
    /** A customer of fiado with what they owe and what they can still buy on fiado right now. */
    public function __construct(
        public string $id,
        public string $name,
        public bool $active,
        public Money $balance,
        public Money $available,
    ) {}
}
