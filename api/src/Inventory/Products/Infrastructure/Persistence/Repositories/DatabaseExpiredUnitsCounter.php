<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Facades\DB;
use Src\Inventory\Products\Application\Contracts\ExpiredUnitsCounter;
use Src\Inventory\Shared\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class DatabaseExpiredUnitsCounter implements ExpiredUnitsCounter
{
    public const string ON_THE_SHELF = 'activa';

    /** Add up the expired units of the product that are still on the shelf. */
    public function of(ProductId $productId, NegocioId $negocioId): int
    {
        return (int) DB::table('unidades_vencidas')
            ->where('negocio_id', $negocioId->value)
            ->where('producto_id', $productId->value)
            ->where('estado', self::ON_THE_SHELF)
            ->sum('cantidad');
    }

    /**
     * Add up the expired units on the shelf of many products with one grouped query.
     *
     * @param  list<ProductId>  $productIds
     * @return array<string, int>
     */
    public function ofMany(array $productIds, NegocioId $negocioId): array
    {
        if ($productIds === []) {
            return [];
        }

        return DB::table('unidades_vencidas')
            ->where('negocio_id', $negocioId->value)
            ->whereIn('producto_id', array_map(fn (ProductId $id): string => $id->value, $productIds))
            ->where('estado', self::ON_THE_SHELF)
            ->groupBy('producto_id')
            ->selectRaw('producto_id, sum(cantidad) as unidades')
            ->pluck('unidades', 'producto_id')
            ->map(fn (mixed $units): int => (int) $units)
            ->all();
    }
}
