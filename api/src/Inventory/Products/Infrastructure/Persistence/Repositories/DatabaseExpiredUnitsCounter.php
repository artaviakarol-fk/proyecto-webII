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
}
