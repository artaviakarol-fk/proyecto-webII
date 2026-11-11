<?php

declare(strict_types=1);

namespace Src\Sales\SaleDrafts\Application\Contracts;

use Src\Sales\SaleDrafts\Application\DTOs\DraftProduct;
use Src\Shared\Domain\ValueObjects\NegocioId;

interface DraftCatalog
{
    /**
     * The live products of the negocio with their price and sellable units.
     *
     * @return list<DraftProduct>
     */
    public function products(NegocioId $negocioId): array;
}
