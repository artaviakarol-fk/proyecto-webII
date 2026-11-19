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

    /**
     * The same count for many products at once, with one query: the units by the id of each product. A product
     * without reservas is not in the answer.
     *
     * @param  list<ProductId>  $productIds
     * @return array<string, int>
     */
    public function ofMany(array $productIds, NegocioId $negocioId, DateTimeImmutable $now): array;
}
