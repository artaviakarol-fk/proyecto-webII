<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Application\UseCases;

use Src\Sales\Sales\Application\DTOs\SaleDetails;
use Src\Sales\Sales\Application\DTOs\SaleLineData;
use Src\Sales\Sales\Domain\Contracts\SaleRepository;
use Src\Sales\Sales\Domain\Entities\Sale;
use Src\Shared\Domain\Contracts\CurrentUser;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class OpenSale
{
    /** Receive the ports of the negocio, the cashier, the transaction and the sales, and the pricing and reading use cases. */
    public function __construct(
        private NegocioContext $context,
        private CurrentUser $user,
        private TransactionRunner $transaction,
        private SaleRepository $sales,
        private PriceSaleLines $priceLines,
        private GetSale $getSale,
    ) {}

    /**
     * Open a cash sale in one transaction: price the lines, take the units out of the stock and record
     * the cashier. If a product does not have enough units, nothing is stored.
     *
     * @param  list<SaleLineData>  $lines
     */
    public function execute(array $lines): SaleDetails
    {
        $negocioId = $this->context->current();

        $sale = $this->transaction->run(function () use ($lines, $negocioId): Sale {
            $sale = Sale::open($this->sales->nextId(), $negocioId, $this->user->id(), $this->priceLines->execute($lines, $negocioId));
            $this->sales->save($sale);

            return $sale;
        });

        return $this->getSale->execute($sale->id->value);
    }
}
