<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Application\UseCases;

use Src\Sales\Sales\Application\DTOs\SaleDetails;
use Src\Sales\Sales\Application\DTOs\SaleLineData;
use Src\Sales\Sales\Domain\Contracts\SaleRepository;
use Src\Sales\Sales\Domain\Exceptions\SaleNotFoundException;
use Src\Sales\Sales\Domain\ValueObjects\BuyerIdNumber;
use Src\Sales\Sales\Domain\ValueObjects\SaleId;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class ReviseSale
{
    /** Receive the ports of the negocio, the transaction and the sales, and the pricing and reading use cases. */
    public function __construct(
        private NegocioContext $context,
        private TransactionRunner $transaction,
        private SaleRepository $sales,
        private PriceSaleLines $priceLines,
        private GetSale $getSale,
    ) {}

    /**
     * Replace the lines of an open sale in one transaction, pricing them again and moving the stock by
     * the difference. A sale that is no longer open cannot change, and one with liquor keeps or gets the id number of the buyer.
     *
     * @param  list<SaleLineData>  $lines
     */
    public function execute(string $saleId, array $lines, ?string $buyerIdNumber = null): SaleDetails
    {
        $negocioId = $this->context->current();

        $this->transaction->run(function () use ($saleId, $lines, $buyerIdNumber, $negocioId): void {
            $sale = $this->sales->findForUpdate(new SaleId($saleId), $negocioId) ?? throw SaleNotFoundException::create();
            $sale->revise(
                $this->priceLines->execute($lines, $negocioId),
                $buyerIdNumber === null || trim($buyerIdNumber) === '' ? null : new BuyerIdNumber($buyerIdNumber),
            );
            $this->sales->save($sale);
        });

        return $this->getSale->execute($saleId);
    }
}
