<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Src\Inventory\Products\Application\Contracts\ReservedUnitsCounter;
use Src\Inventory\Shared\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class DatabaseReservedUnitsCounter implements ReservedUnitsCounter
{
    public const string ACTIVE = 'activa';

    /** Add up the active reservas of the product whose time is not over. */
    public function of(ProductId $productId, NegocioId $negocioId, DateTimeImmutable $now): int
    {
        return (int) DB::table('reservas')
            ->where('negocio_id', $negocioId->value)
            ->where('producto_id', $productId->value)
            ->where('estado', self::ACTIVE)
            ->where('expira_en', '>', $now)
            ->sum('cantidad');
    }

    /**
     * Add up the active reservas of many products with one grouped query.
     *
     * @param  list<ProductId>  $productIds
     * @return array<string, int>
     */
    public function ofMany(array $productIds, NegocioId $negocioId, DateTimeImmutable $now): array
    {
        if ($productIds === []) {
            return [];
        }

        return DB::table('reservas')
            ->where('negocio_id', $negocioId->value)
            ->whereIn('producto_id', array_map(fn (ProductId $id): string => $id->value, $productIds))
            ->where('estado', self::ACTIVE)
            ->where('expira_en', '>', $now)
            ->groupBy('producto_id')
            ->selectRaw('producto_id, sum(cantidad) as unidades')
            ->pluck('unidades', 'producto_id')
            ->map(fn (mixed $units): int => (int) $units)
            ->all();
    }
}
