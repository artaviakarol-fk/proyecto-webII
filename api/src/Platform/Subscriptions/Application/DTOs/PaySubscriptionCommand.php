<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Application\DTOs;

final readonly class PaySubscriptionCommand
{
    /** How many months, with which provider and way of paying, and the payment method the client created if needed. */
    public function __construct(
        public int $months,
        public string $provider,
        public string $method,
        public ?string $methodToken,
    ) {}
}
