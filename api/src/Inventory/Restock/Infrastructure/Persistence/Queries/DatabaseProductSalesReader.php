<?php

declare(strict_types=1);

namespace Src\Inventory\Restock\Infrastructure\Persistence\Queries;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Src\Inventory\Restock\Application\Contracts\ProductSalesReader;
use Src\Inventory\Restock\Application\DTOs\ProductSales;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class DatabaseProductSalesReader implements ProductSalesReader
{
    /**
     * Read every live product with the units of its paid sales since the moment, in one query: the lines of the sales
     * that were not paid or were cancelled stay out of the sum, and a product with no sales keeps a zero.
     *
     * @return list<ProductSales>
     */
    public function soldSince(NegocioId $negocioId, DateTimeImmutable $since): array
    {
        $rows = DB::table('productos as p')
            ->leftJoin('venta_lineas as l', 'l.producto_id', '=', 'p.id')
            ->leftJoin('ventas as v', function ($join) use ($since): void {
                $join->on('v.id', '=', 'l.venta_id')
                    ->where('v.estado', 'cobrada')
                    ->where('v.cobrada_at', '>=', $since);
            })
            ->where('p.negocio_id', $negocioId->value)
            ->whereNull('p.deleted_at')
            ->groupBy('p.id', 'p.codigo', 'p.nombre', 'p.categoria', 'p.stock')
            ->selectRaw('p.id, p.codigo, p.nombre, p.categoria, p.stock, coalesce(sum(case when v.id is not null then l.cantidad else 0 end), 0) as vendidas')
            ->get();

        return array_values($rows->map(static fn (object $row): ProductSales => new ProductSales(
            (string) $row->id,
            (string) $row->codigo,
            (string) $row->nombre,
            $row->categoria === null ? null : (string) $row->categoria,
            (int) $row->stock,
            (int) $row->vendidas,
        ))->all());
    }
}
