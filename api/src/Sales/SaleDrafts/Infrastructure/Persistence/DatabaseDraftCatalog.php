<?php

declare(strict_types=1);

namespace Src\Sales\SaleDrafts\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Sales\SaleDrafts\Application\Contracts\DraftCatalog;
use Src\Sales\SaleDrafts\Application\DTOs\DraftProduct;
use Src\Shared\Domain\ValueObjects\Money;
use Src\Shared\Domain\ValueObjects\NegocioId;

/** Reads the live products of the negocio with their sellable units: the stock minus the expired units on the shelf. */
final readonly class DatabaseDraftCatalog implements DraftCatalog
{
    /** The products, ordered by name. */
    public function products(NegocioId $negocioId): array
    {
        $expired = DB::table('unidades_vencidas')
            ->where('negocio_id', $negocioId->value)
            ->where('estado', 'activa')
            ->groupBy('producto_id')
            ->select('producto_id', DB::raw('sum(cantidad) as vencidas'));

        $rows = DB::table('productos as p')
            ->leftJoinSub($expired, 'v', 'v.producto_id', '=', 'p.id')
            ->where('p.negocio_id', $negocioId->value)
            ->whereNull('p.deleted_at')
            ->orderBy('p.nombre')
            ->get(['p.id', 'p.nombre', 'p.precio_venta', 'p.stock', 'v.vencidas']);

        $products = [];

        foreach ($rows as $row) {
            $products[] = new DraftProduct(
                (string) $row->id,
                (string) $row->nombre,
                Money::fromDecimal((string) $row->precio_venta),
                max(0, (int) $row->stock - (int) $row->vencidas),
            );
        }

        return $products;
    }
}
