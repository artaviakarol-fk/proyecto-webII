<?php

declare(strict_types=1);

namespace Src\Inventory\Expirations\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Inventory\Expirations\Domain\Contracts\ExpiredUnitsRepository;
use Src\Inventory\Expirations\Domain\Entities\ExpiredUnits;
use Src\Inventory\Expirations\Domain\Exceptions\ExpiredUnitsExceedSellableException;
use Src\Inventory\Expirations\Domain\ValueObjects\ExpiredUnitsId;
use Src\Inventory\Products\Domain\Exceptions\ProductNotFoundException;

final readonly class DatabaseExpiredUnitsRepository implements ExpiredUnitsRepository
{
    /** Generate a time-ordered UUID for new expired units. */
    public function nextId(): ExpiredUnitsId
    {
        return new ExpiredUnitsId((string) Str::uuid7());
    }

    /**
     * Store the expired units after locking the product row with SELECT ... FOR UPDATE, the same lock a
     * sale takes, and check that the product still has that many units without a mark.
     */
    public function save(ExpiredUnits $units): void
    {
        DB::transaction(function () use ($units): void {
            $negocioId = $units->negocioId->value;
            $productId = $units->productId->value;

            $product = DB::table('productos')
                ->where('negocio_id', $negocioId)
                ->where('id', $productId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first() ?? throw ProductNotFoundException::create();

            $marked = (int) DB::table('unidades_vencidas')
                ->where('negocio_id', $negocioId)
                ->where('producto_id', $productId)
                ->where('estado', 'activa')
                ->sum('cantidad');

            $available = max(0, (int) $product->stock - $marked);

            if ($units->quantity->value > $available) {
                throw ExpiredUnitsExceedSellableException::create($units->quantity->value, $available);
            }

            DB::table('unidades_vencidas')->insert([
                'id' => $units->id->value,
                'negocio_id' => $negocioId,
                'producto_id' => $productId,
                'cantidad' => $units->quantity->value,
                'motivo' => $units->reason->value,
                'estado' => $units->status->value,
                'usuario_id' => $units->markedBy,
                'created_at' => $units->markedAt,
                'updated_at' => $units->markedAt,
            ]);
        });
    }
}
