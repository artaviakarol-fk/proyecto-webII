<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Application\DTOs;

use DateTimeImmutable;
use Src\Platform\Subscriptions\Domain\Entities\SubscriptionPayment;
use Src\Platform\Subscriptions\Domain\ValueObjects\ServiceStatus;
use Src\Shared\Domain\ValueObjects\Money;

final readonly class SubscriptionView
{
    /**
     * The rent of a negocio as its administrator sees it: where it is, until when it is paid, how many days are left,
     * what a month costs and the last payments.
     *
     * @param  list<SubscriptionPayment>  $payments
     */
    public function __construct(
        public ServiceStatus $status,
        public DateTimeImmutable $expiresAt,
        public int $daysLeft,
        public bool $trial,
        public Money $monthlyPrice,
        public int $graceDays,
        public array $payments,
    ) {}
}
