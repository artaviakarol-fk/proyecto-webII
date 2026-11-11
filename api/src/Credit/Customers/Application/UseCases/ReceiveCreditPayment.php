<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\UseCases;

use DateTimeImmutable;
use Src\Credit\Customers\Application\Contracts\CreditPaymentReader;
use Src\Credit\Customers\Application\Contracts\FiadoBalanceNotifier;
use Src\Credit\Customers\Application\DTOs\ReceivedPayment;
use Src\Credit\Customers\Domain\Contracts\CreditPaymentRepository;
use Src\Credit\Customers\Domain\Contracts\CustomerRepository;
use Src\Credit\Customers\Domain\Contracts\FiadoLedger;
use Src\Credit\Customers\Domain\Exceptions\CreditPaymentNotFoundException;
use Src\Credit\Customers\Domain\Exceptions\CustomerNotFoundException;
use Src\Credit\Customers\Domain\ValueObjects\CustomerId;
use Src\Shared\Domain\Contracts\CurrentUser;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\Money;

/** US-305: the cashier receives an abono (part or all of what the customer owes) and gives a receipt. */
final readonly class ReceiveCreditPayment
{
    /** Receive the ports of the negocio, the cashier, the transaction, the customers, the abonos, the ledger and the notice. */
    public function __construct(
        private NegocioContext $context,
        private CurrentUser $user,
        private TransactionRunner $transaction,
        private CustomerRepository $customers,
        private CreditPaymentRepository $payments,
        private CreditPaymentReader $reader,
        private FiadoLedger $ledger,
        private FiadoBalanceNotifier $notifier,
    ) {}

    /**
     * Record the abono in one transaction. The row of the customer is locked first, so two abonos at
     * the same time, or an abono and a sale on fiado, see the balance one after the other.
     */
    public function execute(string $customerId, string $amount, string $method, DateTimeImmutable $at): ReceivedPayment
    {
        $negocioId = $this->context->current();
        $id = new CustomerId($customerId);

        return $this->transaction->run(function () use ($id, $amount, $method, $at, $negocioId): ReceivedPayment {
            $customer = $this->customers->findForUpdate($id, $negocioId) ?? throw CustomerNotFoundException::create();

            $payment = $customer->receivePayment($this->payments->nextId(), Money::fromDecimal($amount), $method, $this->user->id(), $at);
            $this->payments->add($payment);
            $this->ledger->recordPayment($payment);

            $customer = $this->customers->find($id, $negocioId) ?? throw CustomerNotFoundException::create();
            $this->notifier->balanceChanged($customer);

            $receipt = $this->reader->find($payment->id, $negocioId) ?? throw CreditPaymentNotFoundException::create();

            return new ReceivedPayment($receipt, $customer);
        });
    }
}
