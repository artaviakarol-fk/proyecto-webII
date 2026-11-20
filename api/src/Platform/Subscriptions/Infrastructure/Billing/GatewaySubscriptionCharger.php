<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Infrastructure\Billing;

use Src\Billing\Payments\Domain\Exceptions\InvalidWebhookException;
use Src\Billing\Payments\Domain\ValueObjects\ChargeReference;
use Src\Billing\Payments\Domain\ValueObjects\ChargeRequest;
use Src\Billing\Payments\Domain\ValueObjects\ChargeResult;
use Src\Billing\Payments\Domain\ValueObjects\PaymentId;
use Src\Billing\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Billing\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Billing\Payments\Domain\ValueObjects\SaleId;
use Src\Billing\Payments\Infrastructure\Gateways\PaymentGateways;
use Src\Platform\Subscriptions\Application\Contracts\SubscriptionCharger;
use Src\Platform\Subscriptions\Application\DTOs\ChargeOutcome;
use Src\Platform\Subscriptions\Domain\Entities\SubscriptionPayment;
use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionPaymentStatus;

/**
 * Charges the rent with the same gateways the sales use (ONVO and PayPal), so the platform has a single integration
 * with each provider. The providers know a charge by the order it belongs to; for the rent the order is the payment
 * of the rent itself.
 */
final readonly class GatewaySubscriptionCharger implements SubscriptionCharger
{
    /** Receive the gateways of the providers. */
    public function __construct(private PaymentGateways $gateways) {}

    /** Charge the months of the rent with the chosen provider. */
    public function charge(SubscriptionPayment $payment, ?string $methodToken): ChargeOutcome
    {
        $result = $this->gateways->make($payment->provider)->charge(new ChargeRequest(
            new PaymentId($payment->id),
            new SaleId($payment->id),
            $payment->amount,
            PaymentMethod::from($payment->method->value),
            $methodToken ?? '',
            $payment->months === 1 ? 'TuMiniSúper: 1 mes de servicio' : "TuMiniSúper: {$payment->months} meses de servicio",
        ));

        return $this->outcome($result);
    }

    /** Ask the provider what happened with a charge it already knows. */
    public function status(string $provider, string $reference): ChargeOutcome
    {
        return $this->outcome($this->gateways->make($provider)->status(new ChargeReference($reference)));
    }

    /**
     * The charge a notification talks about, once the provider proved it is its own.
     *
     * @param  array<string, string>  $headers
     */
    public function webhookReference(string $provider, string $payload, array $headers): ?string
    {
        $gateway = $this->gateways->make($provider);
        if (! $gateway->verifyWebhook($payload, $headers)) {
            throw InvalidWebhookException::create();
        }

        return $gateway->parseWebhook($payload)?->reference->value;
    }

    /** Translate the answer of the gateway to the terms of the rent. */
    private function outcome(ChargeResult $result): ChargeOutcome
    {
        $approvalUrl = $result->payload['approval_url'] ?? null;

        return new ChargeOutcome(
            match ($result->status) {
                PaymentStatus::Paid => SubscriptionPaymentStatus::Approved,
                PaymentStatus::Failed => SubscriptionPaymentStatus::Rejected,
                default => SubscriptionPaymentStatus::Pending,
            },
            $result->reference->value,
            is_string($approvalUrl) ? $approvalUrl : null,
            $result->payload,
        );
    }
}
