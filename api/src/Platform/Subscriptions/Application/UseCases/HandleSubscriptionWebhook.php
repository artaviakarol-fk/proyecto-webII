<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Application\UseCases;

use DateTimeImmutable;
use Src\Platform\Subscriptions\Application\Contracts\SubscriptionCharger;
use Src\Platform\Subscriptions\Application\SubscriptionLedger;
use Src\Platform\Subscriptions\Domain\Contracts\SubscriptionPaymentRepository;
use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionPaymentStatus;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class HandleSubscriptionWebhook
{
    /** Receive the payments of the rent, the providers, the ledger and the transaction. */
    public function __construct(
        private SubscriptionPaymentRepository $payments,
        private SubscriptionCharger $charger,
        private SubscriptionLedger $ledger,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Take a notification of a provider about a payment of the rent. It is trusted only when the provider proves it
     * is its own, and even then it is only a hint: the state is asked to the provider. The payment is locked and
     * only a pending one changes, so a notification that arrives twice adds the months once.
     *
     * @param  array<string, string>  $headers
     */
    public function execute(string $provider, string $payload, array $headers, DateTimeImmutable $now): void
    {
        $reference = $this->charger->webhookReference($provider, $payload, $headers);
        if ($reference === null) {
            return;
        }

        $this->transaction->run(function () use ($provider, $reference, $now): void {
            $payment = $this->payments->findByReferenceForUpdate($provider, $reference);
            if ($payment === null || $payment->status !== SubscriptionPaymentStatus::Pending) {
                return;
            }

            $outcome = $this->charger->status($provider, $reference);
            $payment->settle($outcome->status, $outcome->reference, $outcome->answer);
            $this->ledger->record($payment, $now);
        });
    }
}
