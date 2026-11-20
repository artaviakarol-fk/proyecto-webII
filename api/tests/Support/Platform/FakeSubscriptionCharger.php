<?php

declare(strict_types=1);

namespace Tests\Support\Platform;

use Src\Billing\Payments\Domain\Exceptions\InvalidWebhookException;
use Src\Platform\Subscriptions\Application\Contracts\SubscriptionCharger;
use Src\Platform\Subscriptions\Application\DTOs\ChargeOutcome;
use Src\Platform\Subscriptions\Domain\Entities\SubscriptionPayment;
use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionPaymentStatus;

/** A provider of payments that answers what the test tells it, without calling ONVO or PayPal. */
final class FakeSubscriptionCharger implements SubscriptionCharger
{
    /** What the provider answers when charging. */
    public SubscriptionPaymentStatus $charges = SubscriptionPaymentStatus::Approved;

    /** What the provider answers when asked about a charge. */
    public SubscriptionPaymentStatus $reports = SubscriptionPaymentStatus::Approved;

    /** Whether the notifications are proved to come from the provider. */
    public bool $trustsWebhooks = true;

    /** How many times the state was asked. */
    public int $asked = 0;

    public function charge(SubscriptionPayment $payment, ?string $methodToken): ChargeOutcome
    {
        $approvalUrl = $this->charges === SubscriptionPaymentStatus::Pending && $payment->provider === 'paypal'
            ? 'https://paypal.test/aprobar/'.$payment->id
            : null;

        return new ChargeOutcome($this->charges, 'ref-'.$payment->id, $approvalUrl, $approvalUrl === null ? [] : ['approval_url' => $approvalUrl]);
    }

    public function status(string $provider, string $reference): ChargeOutcome
    {
        $this->asked++;

        return new ChargeOutcome($this->reports, $reference, null, []);
    }

    /** @param  array<string, string>  $headers */
    public function webhookReference(string $provider, string $payload, array $headers): ?string
    {
        if (! $this->trustsWebhooks) {
            throw InvalidWebhookException::create();
        }

        $reference = json_decode($payload, true)['referencia'] ?? null;

        return is_string($reference) ? $reference : null;
    }
}
