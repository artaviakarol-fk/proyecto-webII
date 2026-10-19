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
use Src\Inventory\Receipts\Domain\ValueObjects\ReceiptLine;
use Src\Inventory\Shared\Infrastructure\Persistence\PriceHistory;

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
     * All the rows are locked first, in the order of their ids, the same order the sales use: locking
     * them in the order of the lines would let a receipt and a sale wait for each other (a deadlock).
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

            $this->lockProducts($negocioId, $receipt);

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

                // A product created by this same receipt has no previous cost to compare with.
                $previousCost = $line->newProduct ? null : $product->costo_actual;
                $product->update(['stock' => $stock, 'costo_actual' => $line->unitCost->toDecimal()]);

                // El historial guarda el costo de cada entrada; el precio de venta no cambia aquí:
                // lo decide la administración después de ver la diferencia (PATCH /productos/{id}/precio).
                PriceHistory::record(
                    $negocioId,
                    $product->id,
                    $line->unitCost->toDecimal(),
                    $product->precio_venta,
                    PriceHistory::RECEIVED,
                    $receipt->receivedBy,
                    $receipt->id->value,
                );

                DB::table('entrada_lineas')->insert([
                    'id' => (string) Str::uuid7(),
                    'negocio_id' => $negocioId,
                    'entrada_id' => $receipt->id->value,
                    'producto_id' => $product->id,
                    'cantidad' => $line->quantity,
                    'costo_unitario' => $line->unitCost->toDecimal(),
                    'costo_anterior' => $previousCost,
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

    /** Lock the product rows of the receipt, one by one, in the order of their ids. */
    private function lockProducts(string $negocioId, GoodsReceipt $receipt): void
    {
        $productIds = array_values(array_unique(array_map(
            fn (ReceiptLine $line): string => $line->productId->value,
            $receipt->lines,
        )));
        sort($productIds);

        foreach ($productIds as $productId) {
            ProductModel::query()
                ->where('negocio_id', $negocioId)
                ->whereKey($productId)
                ->lockForUpdate()
                ->first(['id']) ?? throw ProductNotFoundException::create();
        }
    }
}
