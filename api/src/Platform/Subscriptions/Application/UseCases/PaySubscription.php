<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Application\UseCases;

use DateTimeImmutable;
use Src\Platform\Subscriptions\Application\Contracts\SubscriptionCharger;
use Src\Platform\Subscriptions\Application\DTOs\ChargeOutcome;
use Src\Platform\Subscriptions\Application\DTOs\PaySubscriptionCommand;
use Src\Platform\Subscriptions\Application\SubscriptionLedger;
use Src\Platform\Subscriptions\Domain\Contracts\SubscriptionPaymentRepository;
use Src\Platform\Subscriptions\Domain\Entities\SubscriptionPayment;
use Src\Platform\Subscriptions\Domain\Exceptions\InvalidSubscriptionPaymentException;
use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionPaymentMethod;
use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionTerms;
use Src\Shared\Domain\Contracts\CurrentUser;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class PaySubscription
{
    /** Receive the negocio and account of the request, the payments, the providers, the ledger, the terms and the transaction. */
    public function __construct(
        private NegocioContext $context,
        private CurrentUser $user,
        private SubscriptionPaymentRepository $payments,
        private SubscriptionCharger $charger,
        private SubscriptionLedger $ledger,
        private SubscriptionTerms $terms,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Pay some months of the rent with ONVO or PayPal. The payment is stored as pending first, so a provider that
     * charges but whose answer is lost can still be followed. When the provider approves it at once, the months are
     * added now; otherwise when the provider confirms it (by asking again or by its notification).
     *
     * @return array{SubscriptionPayment, ?ChargeOutcome}
     */
    public function execute(PaySubscriptionCommand $command, DateTimeImmutable $now): array
    {
        $method = SubscriptionPaymentMethod::from($command->method);
        if ($method->isManual()) {
            throw InvalidSubscriptionPaymentException::manualMethod();
        }

        $payment = SubscriptionPayment::throughProvider(
            $this->payments->nextId(),
            $this->context->current(),
            $this->terms->monthlyPrice->times($command->months),
            $command->months,
            $method,
            $command->provider,
            $this->user->id(),
            $now,
        );
        $this->payments->save($payment);

        $outcome = $this->charger->charge($payment, $command->methodToken);

        $this->transaction->run(function () use ($payment, $outcome, $now): void {
            $payment->settle($outcome->status, $outcome->reference, $outcome->answer);
            $this->ledger->record($payment, $now);
        });

        return [$payment, $outcome];
    }
}
