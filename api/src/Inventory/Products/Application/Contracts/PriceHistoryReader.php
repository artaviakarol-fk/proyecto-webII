<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Application\Contracts;

use Src\Inventory\Products\Application\DTOs\PriceHistoryEntry;
use Src\Inventory\Shared\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\ValueObjects\NegocioId;

interface PriceHistoryReader
{
    /**
     * The cost and price history of a product of the negocio, newest first.
     *
     * @return list<PriceHistoryEntry>
     */
    public function of(ProductId $productId, NegocioId $negocioId): array;
}
