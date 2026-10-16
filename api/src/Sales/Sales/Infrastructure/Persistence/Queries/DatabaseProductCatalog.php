<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Infrastructure\Persistence\Queries;

use Illuminate\Support\Facades\DB;
use Src\Sales\Sales\Application\Contracts\ProductCatalog;
use Src\Sales\Sales\Application\DTOs\CatalogProduct;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class DatabaseProductCatalog implements ProductCatalog
{
    /**
     * Read the live products of the negocio with the given ids.
     *
     * @param  list<string>  $productIds
     * @return array<string, CatalogProduct>
     */
    public function findMany(array $productIds, NegocioId $negocioId): array
    {
        $products = [];

        $rows = DB::table('productos')
            ->where('negocio_id', $negocioId->value)
            ->whereIn('id', $productIds)
            ->whereNull('deleted_at')
            ->get(['id', 'nombre', 'precio_venta']);

        foreach ($rows as $row) {
            $products[(string) $row->id] = new CatalogProduct((string) $row->id, (string) $row->nombre, (string) $row->precio_venta);
        }

        return $products;
    }
}
