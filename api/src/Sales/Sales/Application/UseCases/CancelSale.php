<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Application\UseCases;

use Src\Sales\Sales\Application\Contracts\FiadoCharges;
use Src\Sales\Sales\Application\DTOs\SaleDetails;
use Src\Sales\Sales\Domain\Contracts\SaleRepository;
use Src\Sales\Sales\Domain\Exceptions\SaleNotFoundException;
use Src\Sales\Sales\Domain\ValueObjects\SaleId;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class CancelSale
{
    /** Receive the ports of the negocio, the transaction, the sales and the fiado, and the reading use case. */
    public function __construct(
        private NegocioContext $context,
        private TransactionRunner $transaction,
        private SaleRepository $sales,
        private FiadoCharges $fiado,
        private GetSale $getSale,
    ) {}

    /**
     * Cancel a sale and give its units back to the stock in the same transaction; a sale on fiado
     * also gives its total back to the customer who owed it.
     */
    public function execute(string $saleId): SaleDetails
    {
        $negocioId = $this->context->current();

        $this->transaction->run(function () use ($saleId, $negocioId): void {
            $sale = $this->sales->findForUpdate(new SaleId($saleId), $negocioId) ?? throw SaleNotFoundException::create();
            $wasOnFiado = $sale->isOnFiado();
            $sale->cancel();
            $this->sales->save($sale);

            if ($wasOnFiado) {
                $this->fiado->reverse($sale->id);
            }
        });

        return $this->getSale->execute($saleId);
    }
}
