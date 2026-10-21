<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Application\Contracts;

use Src\Sales\Sales\Application\DTOs\SaleDetails;
use Src\Sales\Sales\Application\DTOs\SaleFilter;
use Src\Sales\Sales\Application\DTOs\SaleSummary;
use Src\Sales\Sales\Domain\ValueObjects\SaleId;
use Src\Shared\Domain\ValueObjects\NegocioId;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;

interface SaleReader
{
    /** Read a sale of the negocio with its lines, the product names and the cashier. */
    public function find(SaleId $id, NegocioId $negocioId): ?SaleDetails;

    /**
     * One page of the sales of the negocio, newest first.
     *
     * @return Page<SaleSummary>
     */
    public function list(NegocioId $negocioId, SaleFilter $filter, PageRequest $page): Page;
}
