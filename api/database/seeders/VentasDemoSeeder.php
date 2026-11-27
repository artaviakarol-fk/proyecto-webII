<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Shared\Infrastructure\Tenancy\NegocioTransaction;

/**
 * Paid cash sales of the last 30 days, so the restock report of the demo has history to read.
 *
 * Each product sells about the pace written in ProductosDemoSeeder::PRODUCTS. The sales do not touch the stock:
 * the demo stocks are fixed on purpose. Every row has an id built from the negocio, the day and the basket, so
 * running the seeder again only adds the days that are missing.
 */
final class VentasDemoSeeder extends Seeder
{
    private const int DAYS = 30;

    /** Baskets sold each day; a product always goes in the same one. */
    private const int BASKETS = 3;

    /** Seed the sales of each demo negocio, each one under its own row-level security scope. */
    public function run(NegocioTransaction $transaction): void
    {
        foreach ([NegociosDemoSeeder::TERESITA_ID, NegociosDemoSeeder::LA_ESQUINA_ID] as $negocioId) {
            $transaction->run($negocioId, function () use ($negocioId): void {
                $cashier = UserModel::query()->where('negocio_id', $negocioId)->where('rol', 'cajero')->orderBy('correo')->first();

                if ($cashier === null) {
                    return;
                }

                $products = ProductModel::query()->where('negocio_id', $negocioId)->get()->keyBy('codigo');

                for ($daysAgo = 1; $daysAgo <= self::DAYS; $daysAgo++) {
                    $day = now()->subDays($daysAgo)->startOfDay();

                    for ($basket = 0; $basket < self::BASKETS; $basket++) {
                        $this->sell($negocioId, (string) $cashier->id, $products, $day, $basket);
                    }
                }
            });
        }
    }

    /**
     * Store one basket of the day, with the products that go in it.
     *
     * @param  Collection<string, ProductModel>  $products
     */
    private function sell(string $negocioId, string $cashierId, Collection $products, CarbonInterface $day, int $basket): void
    {
        $saleId = Uuid::uuid5(Uuid::NAMESPACE_URL, "demo-venta/$negocioId/{$day->toDateString()}/$basket")->toString();
        $paidAt = $day->copy()->setTime(9 + $basket * 4, 15);
        $lines = [];
        $total = 0.0;

        foreach (ProductosDemoSeeder::PRODUCTS as $index => $row) {
            $code = $row[0];
            $product = $products->get($code);

            if ($product === null || $index % self::BASKETS !== $basket) {
                continue;
            }

            $quantity = $this->quantity($code, $day, (float) $row[8]);

            if ($quantity === 0) {
                continue;
            }

            $price = (float) $product->precio_venta;
            $total += $price * $quantity;
            $lines[] = [
                'id' => Uuid::uuid5(Uuid::NAMESPACE_URL, "demo-linea/$saleId/$code")->toString(),
                'negocio_id' => $negocioId,
                'venta_id' => $saleId,
                'producto_id' => $product->id,
                'posicion' => count($lines),
                'cantidad' => $quantity,
                'precio_unitario' => number_format($price, 2, '.', ''),
                'subtotal' => number_format($price * $quantity, 2, '.', ''),
                'created_at' => $paidAt,
                'updated_at' => $paidAt,
            ];
        }

        if ($lines === []) {
            return;
        }

        DB::table('ventas')->insertOrIgnore([
            'id' => $saleId,
            'negocio_id' => $negocioId,
            'cajero_id' => $cashierId,
            'metodo_pago' => 'efectivo',
            'estado' => 'cobrada',
            'total' => number_format($total, 2, '.', ''),
            'cobrada_at' => $paidAt,
            'created_at' => $paidAt,
            'updated_at' => $paidAt,
        ]);
        DB::table('venta_lineas')->insertOrIgnore($lines);
    }

    /** Units of a product sold that day: the whole part of its daily pace, plus one some days for the decimals. */
    private function quantity(string $code, CarbonInterface $day, float $pace): int
    {
        $whole = (int) floor($pace);
        $chance = (int) round(($pace - $whole) * 100);
        $dice = crc32($code.$day->toDateString()) % 100;

        return $whole + ($dice < $chance ? 1 : 0);
    }
}
