<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Application;

use DateTimeImmutable;
use Src\Platform\Subscriptions\Domain\Contracts\SubscriptionPaymentRepository;
use Src\Platform\Subscriptions\Domain\Contracts\SubscriptionRepository;
use Src\Platform\Subscriptions\Domain\Entities\SubscriptionPayment;
use Src\Platform\Subscriptions\Domain\Exceptions\SubscriptionNotFoundException;

/** Adds the months of an approved payment to the rent, exactly once. */
final readonly class SubscriptionLedger
{
    /** Receive the rents and their payments. */
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private SubscriptionPaymentRepository $payments,
    ) {}

    /**
     * Store the payment and, when it was just approved, extend the rent with its months. It must run inside the
     * transaction that locked the payment, so the months are never added twice.
     */
    public function record(SubscriptionPayment $payment, DateTimeImmutable $now): void
    {
        if ($payment->mustApply()) {
            $subscription = $this->subscriptions->findForUpdate($payment->negocioId) ?? throw SubscriptionNotFoundException::create();
            $this->subscriptions->save($subscription->extend($payment->months, $now));
            $payment->markApplied($now);
        }

        $this->payments->save($payment);
    }
}
