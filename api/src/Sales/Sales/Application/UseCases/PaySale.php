<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Application\UseCases;

use Src\Sales\Sales\Application\DTOs\SaleDetails;
use Src\Sales\Sales\Domain\Contracts\SaleRepository;
use Src\Sales\Sales\Domain\Entities\Sale;
use Src\Sales\Sales\Domain\Exceptions\SaleNotFoundException;
use Src\Sales\Sales\Domain\ValueObjects\SaleId;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class PaySale
{
    /** Receive the ports of the negocio, the transaction and the sales, and the reading use case. */
    public function __construct(
        private NegocioContext $context,
        private TransactionRunner $transaction,
        private SaleRepository $sales,
        private GetSale $getSale,
    ) {}

    /** Close an open sale as paid (in cash unless a provider took the payment); from then on it cannot change. */
    public function execute(string $saleId, string $method = Sale::CASH): SaleDetails
    {
        $negocioId = $this->context->current();

        $this->transaction->run(function () use ($saleId, $negocioId, $method): void {
            $sale = $this->sales->findForUpdate(new SaleId($saleId), $negocioId) ?? throw SaleNotFoundException::create();
            $sale->pay($method);
            $this->sales->save($sale);
        });

        return $this->getSale->execute($saleId);
    }
}
