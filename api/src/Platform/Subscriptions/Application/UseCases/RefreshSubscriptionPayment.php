<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Application\UseCases;

use DateTimeImmutable;
use Src\Platform\Subscriptions\Application\Contracts\SubscriptionCharger;
use Src\Platform\Subscriptions\Application\SubscriptionLedger;
use Src\Platform\Subscriptions\Domain\Contracts\SubscriptionPaymentRepository;
use Src\Platform\Subscriptions\Domain\Entities\SubscriptionPayment;
use Src\Platform\Subscriptions\Domain\Exceptions\SubscriptionNotFoundException;
use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionPaymentStatus;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class RefreshSubscriptionPayment
{
    /** Receive the negocio of the request, its payments, the providers, the ledger and the transaction. */
    public function __construct(
        private NegocioContext $context,
        private SubscriptionPaymentRepository $payments,
        private SubscriptionCharger $charger,
        private SubscriptionLedger $ledger,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Follow a pending payment of the rent: the state is asked to the provider, never taken from the browser. When
     * the provider says it was paid, its months are added to the rent once.
     */
    public function execute(string $paymentId, DateTimeImmutable $now): SubscriptionPayment
    {
        $negocioId = $this->context->current();

        return $this->transaction->run(function () use ($paymentId, $negocioId, $now): SubscriptionPayment {
            $payment = $this->payments->findForUpdate($paymentId, $negocioId) ?? throw SubscriptionNotFoundException::payment();

            if ($payment->status === SubscriptionPaymentStatus::Pending && $payment->reference !== null) {
                $outcome = $this->charger->status($payment->provider, $payment->reference);
                $payment->settle($outcome->status, $outcome->reference, $outcome->answer);
                $this->ledger->record($payment, $now);
            }

            return $payment;
        });
    }
}
