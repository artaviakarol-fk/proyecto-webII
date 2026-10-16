<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Application\Contracts;

use Src\Sales\Sales\Application\DTOs\CatalogProduct;
use Src\Shared\Domain\ValueObjects\NegocioId;

interface ProductCatalog
{
    /**
     * Read the live products of the negocio with the given ids; the ones that do not exist are left out.
     *
     * @param  list<string>  $productIds
     * @return array<string, CatalogProduct> Indexed by product id.
     */
    public function findMany(array $productIds, NegocioId $negocioId): array;
}
