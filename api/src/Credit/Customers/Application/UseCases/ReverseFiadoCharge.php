<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\UseCases;

use Src\Credit\Customers\Application\Contracts\FiadoBalanceNotifier;
use Src\Credit\Customers\Domain\Contracts\CustomerRepository;
use Src\Credit\Customers\Domain\Contracts\FiadoLedger;
use Src\Credit\Customers\Domain\Exceptions\CustomerNotFoundException;
use Src\Shared\Domain\Contracts\CurrentUser;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\Contracts\TransactionRunner;

/** A cancelled sale on fiado gives its total back: what the customer owes goes down by it. */
final readonly class ReverseFiadoCharge
{
    /** Receive the ports of the negocio, the cashier, the transaction, the customers, the ledger and the notice. */
    public function __construct(
        private NegocioContext $context,
        private CurrentUser $user,
        private TransactionRunner $transaction,
        private CustomerRepository $customers,
        private FiadoLedger $ledger,
        private FiadoBalanceNotifier $notifier,
    ) {}

    /** Give back the charge of the sale, if it has one; it joins the transaction of the cancellation. */
    public function execute(string $saleId): void
    {
        $negocioId = $this->context->current();

        $this->transaction->run(function () use ($saleId, $negocioId): void {
            $charge = $this->ledger->pendingChargeOf($negocioId, $saleId);

            if ($charge === null) {
                return;
            }

            $this->customers->findForUpdate($charge->customerId, $negocioId) ?? throw CustomerNotFoundException::create();
            $this->ledger->recordCancellation($negocioId, $charge, $this->user->id());

            $customer = $this->customers->find($charge->customerId, $negocioId) ?? throw CustomerNotFoundException::create();
            $this->notifier->balanceChanged($customer);
        });
    }
}
