<?php

declare(strict_types=1);

namespace Src\Inventory\Receipts\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Inventory\Products\Domain\Exceptions\ProductNotFoundException;
use Src\Inventory\Products\Domain\ValueObjects\Stock;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Inventory\Receipts\Domain\Contracts\GoodsReceiptRepository;
use Src\Inventory\Receipts\Domain\Entities\GoodsReceipt;
use Src\Inventory\Receipts\Domain\Exceptions\StockLimitExceededException;
use Src\Inventory\Receipts\Domain\ValueObjects\ReceiptId;

final readonly class EloquentGoodsReceiptRepository implements GoodsReceiptRepository
{
    public const string MOVEMENT_TYPE = 'entrada';

    /** Generate a time-ordered UUID for a new receipt. */
    public function nextId(): ReceiptId
    {
        return new ReceiptId((string) Str::uuid7());
    }

    /**
     * Store the receipt and add its lines to the stock in one transaction.
     *
     * Each product row is locked with SELECT ... FOR UPDATE before its stock changes, so two receipts,
     * or a receipt and a sale, that touch the same product run one after the other and none is lost.
     */
    public function save(GoodsReceipt $receipt): void
    {
        DB::transaction(function () use ($receipt): void {
            $negocioId = $receipt->negocioId->value;
            $now = now();

            DB::table('entradas_mercaderia')->insert([
                'id' => $receipt->id->value,
                'negocio_id' => $negocioId,
                'proveedor' => $receipt->supplier->value,
                'fecha' => $receipt->date->format('Y-m-d'),
                'registrada_por' => $receipt->receivedBy,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($receipt->lines as $line) {
                $product = ProductModel::query()
                    ->where('negocio_id', $negocioId)
                    ->whereKey($line->productId->value)
                    ->lockForUpdate()
                    ->first() ?? throw ProductNotFoundException::create();

                $stock = $product->stock + $line->quantity;

                if ($stock > Stock::MAX) {
                    throw StockLimitExceededException::create(Stock::MAX);
                }

                $product->update(['stock' => $stock, 'costo_actual' => $line->unitCost->toDecimal()]);

                DB::table('entrada_lineas')->insert([
                    'id' => (string) Str::uuid7(),
                    'negocio_id' => $negocioId,
                    'entrada_id' => $receipt->id->value,
                    'producto_id' => $product->id,
                    'cantidad' => $line->quantity,
                    'costo_unitario' => $line->unitCost->toDecimal(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('movimientos_inventario')->insert([
                    'id' => (string) Str::uuid7(),
                    'negocio_id' => $negocioId,
                    'producto_id' => $product->id,
                    'tipo' => self::MOVEMENT_TYPE,
                    'cantidad' => $line->quantity,
                    'stock_resultante' => $stock,
                    'referencia_id' => $receipt->id->value,
                    'usuario_id' => $receipt->receivedBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }
}
