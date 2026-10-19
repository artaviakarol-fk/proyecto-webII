<?php

declare(strict_types=1);

namespace Src\Inventory\Shared\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes one row of historial_precios each time the cost or the sale price of a product changes:
 * when it is created, edited, received or repriced. Every module that changes a price goes through here.
 */
final class PriceHistory
{
    public const string CREATED = 'creacion';

    public const string EDITED = 'edicion';

    public const string RECEIVED = 'entrada';

    public const string REPRICED = 'cambio_precio';

    /** Record the cost and sale price a product has from now on, why, and who changed it. */
    public static function record(
        string $negocioId,
        string $productId,
        string $cost,
        string $salePrice,
        string $reason,
        string $userId,
        ?string $referenceId = null,
    ): void {
        $now = now();

        DB::table('historial_precios')->insert([
            'id' => (string) Str::uuid7(),
            'negocio_id' => $negocioId,
            'producto_id' => $productId,
            'costo' => $cost,
            'precio_venta' => $salePrice,
            'motivo' => $reason,
            'referencia_id' => $referenceId,
            'usuario_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
