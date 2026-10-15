<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Sales\Sales\Domain\Contracts\SaleRepository;
use Src\Sales\Sales\Domain\Entities\Sale;
use Src\Sales\Sales\Domain\Exceptions\InsufficientStockException;
use Src\Sales\Sales\Domain\Exceptions\SaleProductUnavailableException;
use Src\Sales\Sales\Domain\ValueObjects\BuyerIdNumber;
use Src\Sales\Sales\Domain\ValueObjects\SaleId;
use Src\Sales\Sales\Domain\ValueObjects\SaleLine;
use Src\Sales\Sales\Domain\ValueObjects\SaleStatus;
use Src\Sales\Shared\Domain\ValueObjects\Money;
use Src\Sales\Shared\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\Contracts\CurrentUser;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class DatabaseSaleRepository implements SaleRepository
{
    public const string PAYMENT_METHOD = 'efectivo';

    public const string SALE_MOVEMENT = 'venta';

    public const string CANCELLATION_MOVEMENT = 'anulacion';

    /** Expired units that are still on the shelf do not count as sellable. */
    public const string EXPIRED_ON_THE_SHELF = 'activa';

    /** Receive the account that performs the operation, to record it in the inventory movements. */
    public function __construct(private CurrentUser $user) {}

    /** Generate a time-ordered UUID for a new sale. */
    public function nextId(): SaleId
    {
        return new SaleId((string) Str::uuid7());
    }

    /** Find a sale of the negocio with its lines, locking its row until the transaction ends. */
    public function findForUpdate(SaleId $id, NegocioId $negocioId): ?Sale
    {
        $row = DB::table('ventas')
            ->where('negocio_id', $negocioId->value)
            ->where('id', $id->value)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            return null;
        }

        $lines = DB::table('venta_lineas')
            ->where('venta_id', $id->value)
            ->orderBy('posicion')
            ->get()
            ->map(fn (object $line): SaleLine => new SaleLine(
                new ProductId((string) $line->producto_id),
                (int) $line->cantidad,
                Money::fromDecimal((string) $line->precio_unitario),
            ))
            ->all();

        return Sale::reconstitute($id, $negocioId, (string) $row->cajero_id, SaleStatus::from((string) $row->estado), array_values($lines), $row->cedula_comprador === null ? null : new BuyerIdNumber((string) $row->cedula_comprador));
    }

    /**
     * Store the sale and move the stock by the difference with what the sale held before, in one transaction.
     *
     * Each product row is locked with SELECT ... FOR UPDATE, always in the same order, before its stock
     * changes: two registers selling the last unit run one after the other, the second finds it gone and
     * gets a 409, and two sales that share products cannot block each other.
     */
    public function save(Sale $sale): void
    {
        DB::transaction(function () use ($sale): void {
            $negocioId = $sale->negocioId->value;
            $saleId = $sale->id->value;
            $now = now();

            $existing = DB::table('ventas')
                ->where('negocio_id', $negocioId)
                ->where('id', $saleId)
                ->lockForUpdate()
                ->first();

            $before = $existing === null || $existing->estado === SaleStatus::Cancelled->value
                ? []
                : $this->heldUnits($saleId);
            $after = $sale->status->holdsStock() ? $this->unitsOf($sale) : [];

            $columns = [
                'cajero_id' => $sale->cashierId,
                'metodo_pago' => self::PAYMENT_METHOD,
                'cedula_comprador' => $sale->buyer?->value,
                'estado' => $sale->status->value,
                'total' => $sale->total()->toDecimal(),
                'cobrada_at' => $sale->status === SaleStatus::Paid ? ($existing->cobrada_at ?? $now) : null,
                'anulada_at' => $sale->status === SaleStatus::Cancelled ? ($existing->anulada_at ?? $now) : null,
                'updated_at' => $now,
            ];

            if ($existing === null) {
                DB::table('ventas')->insert(['id' => $saleId, 'negocio_id' => $negocioId, 'created_at' => $now, ...$columns]);
            } else {
                DB::table('ventas')->where('id', $saleId)->update($columns);
            }

            $this->moveStock($negocioId, $saleId, $before, $after, $now);
            $this->rewriteLines($sale, $now);
        });
    }

    /**
     * The units each product has in the stored sale.
     *
     * @return array<string, int> Indexed by product id.
     */
    private function heldUnits(string $saleId): array
    {
        $units = [];

        foreach (DB::table('venta_lineas')->where('venta_id', $saleId)->get(['producto_id', 'cantidad']) as $line) {
            $units[(string) $line->producto_id] = (int) $line->cantidad;
        }

        return $units;
    }

    /**
     * The units each product has in the sale being saved.
     *
     * @return array<string, int> Indexed by product id.
     */
    private function unitsOf(Sale $sale): array
    {
        $units = [];

        foreach ($sale->lines as $line) {
            $units[$line->productId->value] = $line->quantity;
        }

        return $units;
    }

    /**
     * Take or give back the difference of units of each product, in the order of the product ids.
     *
     * @param  array<string, int>  $before
     * @param  array<string, int>  $after
     */
    private function moveStock(string $negocioId, string $saleId, array $before, array $after, mixed $now): void
    {
        $productIds = array_values(array_unique([...array_keys($before), ...array_keys($after)]));
        sort($productIds);

        foreach ($productIds as $productId) {
            $delta = ($after[$productId] ?? 0) - ($before[$productId] ?? 0);

            if ($delta === 0) {
                continue;
            }

            $product = DB::table('productos')
                ->where('negocio_id', $negocioId)
                ->where('id', $productId)
                ->lockForUpdate()
                ->first();

            // A product deleted after the sale can still get its units back, but it cannot be sold again.
            if ($product === null || ($delta > 0 && $product->deleted_at !== null)) {
                throw SaleProductUnavailableException::create();
            }

            if ($delta > 0) {
                $sellable = (int) $product->stock - $this->expiredUnits($negocioId, $productId);

                if ($sellable < $delta) {
                    throw InsufficientStockException::create((string) $product->nombre, max(0, $sellable));
                }
            }

            $stock = (int) $product->stock - $delta;

            DB::table('productos')->where('id', $productId)->update(['stock' => $stock, 'updated_at' => $now]);

            DB::table('movimientos_inventario')->insert([
                'id' => (string) Str::uuid7(),
                'negocio_id' => $negocioId,
                'producto_id' => $productId,
                'tipo' => $delta > 0 ? self::SALE_MOVEMENT : self::CANCELLATION_MOVEMENT,
                'cantidad' => abs($delta),
                'stock_resultante' => $stock,
                'referencia_id' => $saleId,
                'usuario_id' => $this->user->id(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** Add up the expired units of the product that are still on the shelf. */
    private function expiredUnits(string $negocioId, string $productId): int
    {
        return (int) DB::table('unidades_vencidas')
            ->where('negocio_id', $negocioId)
            ->where('producto_id', $productId)
            ->where('estado', self::EXPIRED_ON_THE_SHELF)
            ->sum('cantidad');
    }

    /** Replace the stored lines of the sale with the current ones, keeping their order. */
    private function rewriteLines(Sale $sale, mixed $now): void
    {
        DB::table('venta_lineas')->where('venta_id', $sale->id->value)->delete();

        foreach ($sale->lines as $position => $line) {
            DB::table('venta_lineas')->insert([
                'id' => (string) Str::uuid7(),
                'negocio_id' => $sale->negocioId->value,
                'venta_id' => $sale->id->value,
                'producto_id' => $line->productId->value,
                'posicion' => $position,
                'cantidad' => $line->quantity,
                'precio_unitario' => $line->unitPrice->toDecimal(),
                'subtotal' => $line->subtotal()->toDecimal(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
