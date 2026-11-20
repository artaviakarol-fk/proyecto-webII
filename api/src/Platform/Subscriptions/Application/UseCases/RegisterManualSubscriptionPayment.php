<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Application\UseCases;

use DateTimeImmutable;
use Src\Platform\Subscriptions\Application\DTOs\ManualPaymentCommand;
use Src\Platform\Subscriptions\Application\SubscriptionLedger;
use Src\Platform\Subscriptions\Domain\Contracts\SubscriptionPaymentRepository;
use Src\Platform\Subscriptions\Domain\Contracts\SubscriptionRepository;
use Src\Platform\Subscriptions\Domain\Entities\SubscriptionPayment;
use Src\Platform\Subscriptions\Domain\Exceptions\InvalidSubscriptionPaymentException;
use Src\Platform\Subscriptions\Domain\Exceptions\SubscriptionNotFoundException;
use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionPaymentMethod;
use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionTerms;
use Src\Shared\Domain\Contracts\CurrentUser;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class RegisterManualSubscriptionPayment
{
    /** Receive the superadmin of the request, the rents, their payments, the ledger, the terms and the transaction. */
    public function __construct(
        private CurrentUser $user,
        private SubscriptionRepository $subscriptions,
        private SubscriptionPaymentRepository $payments,
        private SubscriptionLedger $ledger,
        private SubscriptionTerms $terms,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Register a payment that the platform received in cash or by SINPE to its own number. It is approved at once
     * and its months are added to the rent; the superadmin who registered it is kept with it.
     */
    public function execute(ManualPaymentCommand $command, DateTimeImmutable $now): SubscriptionPayment
    {
        $method = SubscriptionPaymentMethod::from($command->method);
        if (! $method->isManual()) {
            throw InvalidSubscriptionPaymentException::providerMethod();
        }

        $negocioId = new NegocioId($command->negocioId);
        if ($this->subscriptions->find($negocioId) === null) {
            throw SubscriptionNotFoundException::create();
        }

        $payment = SubscriptionPayment::manual(
            $this->payments->nextId(),
            $negocioId,
            $this->terms->monthlyPrice->times($command->months),
            $command->months,
            $method,
            $command->reference,
            $this->user->id(),
            $now,
        );

        $this->transaction->run(fn () => $this->ledger->record($payment, $now));

        return $payment;
    }
}
