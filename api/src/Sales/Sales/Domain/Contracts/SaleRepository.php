<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Domain\Contracts;

use Src\Sales\Sales\Domain\Entities\Sale;
use Src\Sales\Sales\Domain\Exceptions\InsufficientStockException;
use Src\Sales\Sales\Domain\Exceptions\SaleProductUnavailableException;
use Src\Sales\Sales\Domain\ValueObjects\SaleId;
use Src\Shared\Domain\ValueObjects\NegocioId;

interface SaleRepository
{
    /** Generate the identity for a new sale. */
    public function nextId(): SaleId;

    /** Find a sale of the negocio and lock it until the end of the transaction, so two changes to it run one after the other. */
    public function findForUpdate(SaleId $id, NegocioId $negocioId): ?Sale;

    /**
     * Store the sale and move the stock by the difference between what the sale held before and what it
     * holds now, locking each product row, so the stock never goes negative even when two registers sell
     * the last unit at the same time. A cancelled sale holds nothing, so its units go back to the stock.
     *
     * @throws InsufficientStockException
     * @throws SaleProductUnavailableException
     */
    public function save(Sale $sale): void;
}
