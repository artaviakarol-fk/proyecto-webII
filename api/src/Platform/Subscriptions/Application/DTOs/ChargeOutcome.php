<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Application\DTOs;

use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionPaymentStatus;

final readonly class ChargeOutcome
{
    /**
     * What a provider answered about a charge of the rent: its state, its reference and, while it waits for the
     * payer, where to approve it (PayPal). The answer is kept without card data.
     *
     * @param  array<string, mixed>  $answer
     */
    public function __construct(
        public SubscriptionPaymentStatus $status,
        public string $reference,
        public ?string $approvalUrl,
        public array $answer,
    ) {}
}
