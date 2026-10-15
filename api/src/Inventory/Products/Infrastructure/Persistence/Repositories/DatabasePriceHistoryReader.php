<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Infrastructure\Persistence\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Src\Inventory\Products\Application\Contracts\PriceHistoryReader;
use Src\Inventory\Products\Application\DTOs\PriceHistoryEntry;
use Src\Inventory\Shared\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class DatabasePriceHistoryReader implements PriceHistoryReader
{
    /**
     * The cost and price history of a product of the negocio, newest first.
     *
     * @return list<PriceHistoryEntry>
     */
    public function of(ProductId $productId, NegocioId $negocioId): array
    {
        return DB::table('historial_precios as h')
            ->join('users as u', 'u.id', '=', 'h.usuario_id')
            ->where('h.negocio_id', $negocioId->value)
            ->where('h.producto_id', $productId->value)
            ->orderByDesc('h.created_at')
            ->orderByDesc('h.id')
            ->get(['h.created_at', 'h.costo', 'h.precio_venta', 'h.motivo', 'h.referencia_id', 'u.id as usuario_id', 'u.nombre as usuario_nombre'])
            ->map(fn (object $row): PriceHistoryEntry => new PriceHistoryEntry(
                CarbonImmutable::parse((string) $row->created_at)->setTimezone((string) config('app.timezone'))->toIso8601String(),
                (string) $row->costo,
                (string) $row->precio_venta,
                (string) $row->motivo,
                (string) $row->usuario_id,
                (string) $row->usuario_nombre,
                $row->referencia_id === null ? null : (string) $row->referencia_id,
            ))
            ->values()
            ->all();
    }
}
