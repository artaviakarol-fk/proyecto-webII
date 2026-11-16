<?php

declare(strict_types=1);

namespace Src\Sales\SaleDrafts\Application\DTOs;

use Src\Shared\Domain\ValueObjects\Money;

final readonly class DraftProduct
{
    /** A product of the catalog with its price and the units that can really be sold now. */
    public function __construct(
        public string $id,
        public string $name,
        public Money $price,
        public int $sellable,
    ) {}
}
