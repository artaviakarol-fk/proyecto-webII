<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Application\Contracts;

use Src\Platform\Subscriptions\Application\DTOs\ChargeOutcome;
use Src\Platform\Subscriptions\Domain\Entities\SubscriptionPayment;

/** The payment providers of the platform, the same ones the sales use, seen from the rent. */
interface SubscriptionCharger
{
    /** Charge a payment of the rent with its provider, with the payment method the client created when it needs one. */
    public function charge(SubscriptionPayment $payment, ?string $methodToken): ChargeOutcome;

    /** Ask the provider what happened with a charge it already knows. */
    public function status(string $provider, string $reference): ChargeOutcome;

    /**
     * The reference of the charge that a notification of a provider talks about, after checking that it really comes
     * from the provider; null when the notification is not about a charge.
     *
     * @param  array<string, string>  $headers
     */
    public function webhookReference(string $provider, string $payload, array $headers): ?string;
}
