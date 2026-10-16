<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Application\Contracts;

use Src\Sales\Sales\Application\DTOs\SaleDetails;
use Src\Sales\Sales\Domain\ValueObjects\SaleId;
use Src\Shared\Domain\ValueObjects\NegocioId;

interface SaleReader
{
    /** Read a sale of the negocio with its lines, the product names and the cashier. */
    public function find(SaleId $id, NegocioId $negocioId): ?SaleDetails;
}
