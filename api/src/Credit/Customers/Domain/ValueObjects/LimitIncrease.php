<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Domain\ValueObjects;

use DateTimeImmutable;
use Src\Shared\Domain\ValueObjects\Money;

/** An increase of the limit of a customer that was not reverted: how much it adds and until when. */
final readonly class LimitIncrease
{
    /** Hold the amount and the moment it stops counting. */
    public function __construct(
        public Money $amount,
        public DateTimeImmutable $validUntil,
    ) {}

    /** Tell whether it still counts at a moment. */
    public function countsAt(DateTimeImmutable $at): bool
    {
        return $at < $this->validUntil;
    }
}
