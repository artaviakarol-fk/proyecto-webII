<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Application\Contracts;

use DateTimeImmutable;
use Src\Inventory\Shared\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\ValueObjects\NegocioId;

interface ReservedUnitsCounter
{
    /** Units of a product of the negocio that clientes hold right now: active reservas that have not expired. */
    public function of(ProductId $productId, NegocioId $negocioId, DateTimeImmutable $now): int;
}
