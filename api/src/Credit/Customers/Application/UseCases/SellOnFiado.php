<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\UseCases;

use Src\Credit\Customers\Application\Contracts\CreditableSales;
use Src\Credit\Customers\Application\Contracts\FiadoBalanceNotifier;
use Src\Credit\Customers\Application\DTOs\FiadoSale;
use Src\Credit\Customers\Domain\Contracts\CustomerRepository;
use Src\Credit\Customers\Domain\Contracts\FiadoLedger;
use Src\Credit\Customers\Domain\Exceptions\CustomerNotFoundException;
use Src\Credit\Customers\Domain\Exceptions\SaleNotFoundForFiadoException;
use Src\Credit\Customers\Domain\ValueObjects\CustomerId;
use Src\Credit\Customers\Domain\ValueObjects\FiadoCharge;
use Src\Shared\Domain\Contracts\CurrentUser;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\Contracts\TransactionRunner;

/** US-303: the cashier closes an open sale on fiado and its total goes to what the customer owes. */
final readonly class SellOnFiado
{
    /** Receive the ports of the negocio, the cashier, the transaction, the customers, the ledger, the sales and the notice. */
    public function __construct(
        private NegocioContext $context,
        private CurrentUser $user,
        private TransactionRunner $transaction,
        private CustomerRepository $customers,
        private FiadoLedger $ledger,
        private CreditableSales $sales,
        private FiadoBalanceNotifier $notifier,
    ) {}

    /**
     * Charge the sale to the customer in one transaction. The row of the customer is locked first, so
     * two sales to the same customer at the same time are checked against the limit one after the other.
     * If the sale does not fit, nothing changes: the sale stays open and can be paid in another way.
     */
    public function execute(string $saleId, string $customerId): FiadoSale
    {
        $negocioId = $this->context->current();

        return $this->transaction->run(function () use ($saleId, $customerId, $negocioId): FiadoSale {
            $customer = $this->customers->findForUpdate(new CustomerId($customerId), $negocioId)
                ?? throw CustomerNotFoundException::create();
            $sale = $this->sales->find($saleId) ?? throw SaleNotFoundForFiadoException::create();

            $customer->assertCanBuyOnFiado($sale->total);

            $this->sales->markOnFiado($saleId);
            $this->ledger->recordCharge($negocioId, new FiadoCharge($customer->id, $saleId, $sale->total), $this->user->id());

            $customer = $this->customers->find($customer->id, $negocioId) ?? throw CustomerNotFoundException::create();
            $this->notifier->balanceChanged($customer);

            return new FiadoSale($saleId, $sale->total, $customer);
        });
    }
}
