<?php

declare(strict_types=1);

namespace Tests\Support\Factories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Src\Shared\Infrastructure\Tenancy\NegocioTransaction;

/**
 * Builds a stored sale for the tests, like the Eloquent factories do for negocios, users and products.
 *
 * Sales have no Eloquent model (the module writes them through its repository), so this factory inserts the
 * rows directly. It never touches the stock: it only gives a test a sale that already exists, for example one
 * that belongs to another negocio.
 */
final class SaleFactory
{
    private NegocioModel $negocio;

    private UserModel $cashier;

    private string $status = 'abierta';

    /** @var list<array{ProductModel, int}> */
    private array $lines = [];

    /** Start a sale; give it a negocio and a cashier with for(). */
    public static function new(): self
    {
        return new self;
    }

    /** The negocio of the sale and the cashier that sold it. */
    public function for(NegocioModel $negocio, UserModel $cashier): self
    {
        $clone = clone $this;
        $clone->negocio = $negocio;
        $clone->cashier = $cashier;

        return $clone;
    }

    /** Add a line with the units of a product, at the price the product has now. */
    public function line(ProductModel $product, int $quantity): self
    {
        $clone = clone $this;
        $clone->lines[] = [$product, $quantity];

        return $clone;
    }

    /** The sale is already paid. */
    public function paid(): self
    {
        $clone = clone $this;
        $clone->status = 'cobrada';

        return $clone;
    }

    /** The sale is already cancelled. */
    public function cancelled(): self
    {
        $clone = clone $this;
        $clone->status = 'anulada';

        return $clone;
    }

    /** Store the sale with its lines and answer its identifier. */
    public function create(): string
    {
        app(NegocioTransaction::class)->scope($this->negocio->id);

        $id = (string) Str::uuid7();
        $now = now();
        $total = 0.0;
        $lines = [];

        foreach ($this->lines as $position => [$product, $quantity]) {
            $price = (float) $product->precio_venta;
            $total += $price * $quantity;
            $lines[] = [
                'id' => (string) Str::uuid7(),
                'negocio_id' => $this->negocio->id,
                'venta_id' => $id,
                'producto_id' => $product->id,
                'posicion' => $position,
                'cantidad' => $quantity,
                'precio_unitario' => number_format($price, 2, '.', ''),
                'subtotal' => number_format($price * $quantity, 2, '.', ''),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('ventas')->insert([
            'id' => $id,
            'negocio_id' => $this->negocio->id,
            'cajero_id' => $this->cashier->id,
            'metodo_pago' => 'efectivo',
            'estado' => $this->status,
            'total' => number_format($total, 2, '.', ''),
            'cobrada_at' => $this->status === 'cobrada' ? $now : null,
            'anulada_at' => $this->status === 'anulada' ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('venta_lineas')->insert($lines);

        return $id;
    }
}
