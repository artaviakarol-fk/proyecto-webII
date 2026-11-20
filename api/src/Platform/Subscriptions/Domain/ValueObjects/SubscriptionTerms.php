<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\Money;

/** What the platform charges and how long it waits: the price of a month and the days of trial, warning and grace. */
final readonly class SubscriptionTerms
{
    /** Every number must make sense: a price above zero and days that are not negative. */
    public function __construct(
        public Money $monthlyPrice,
        public int $trialDays,
        public int $warningDays,
        public int $graceDays,
    ) {
        if ($monthlyPrice->cents <= 0 || $trialDays < 0 || $warningDays < 0 || $graceDays < 0) {
            throw new InvalidValueException('platform.invalid_terms');
        }
    }
}
