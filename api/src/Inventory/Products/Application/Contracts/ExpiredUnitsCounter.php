<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Application\Contracts;

use Src\Inventory\Shared\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\ValueObjects\NegocioId;

interface ExpiredUnitsCounter
{
    /** Units of a product of the negocio marked as expired and still on the shelf. */
    public function of(ProductId $productId, NegocioId $negocioId): int;
}
