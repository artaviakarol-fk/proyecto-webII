<?php

declare(strict_types=1);

namespace Src\Inventory\Restock\Application\Contracts;

use DateTimeImmutable;
use Src\Inventory\Restock\Application\DTOs\ProductSales;
use Src\Shared\Domain\ValueObjects\NegocioId;

interface ProductSalesReader
{
    /**
     * Every live product of the negocio with its stock and the units of it sold since a moment. Only paid sales count,
     * on the moment they were paid: an open or a cancelled one never does. A product that did not sell is there with zero.
     *
     * @return list<ProductSales>
     */
    public function soldSince(NegocioId $negocioId, DateTimeImmutable $since): array;
}
