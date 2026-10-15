<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Shared\Infrastructure\Tenancy\NegocioTransaction;

final class ProductosDemoSeeder extends Seeder
{
    /**
     * Fictitious products: code, name, category, sale price, cost, stock, perishable, liquor.
     *
     * @var list<array{string, string, string, string, string, int, bool, bool}>
     */
    private const array PRODUCTS = [
        ['7441001100011', 'Arroz 1 kg', 'Abarrotes', '1350.00', '1050.00', 40, false, false],
        ['7441001100028', 'Frijoles negros 900 g', 'Abarrotes', '1600.00', '1250.00', 25, false, false],
        ['7441001100035', 'Leche entera 1 L', 'Lácteos', '950.00', '760.00', 30, true, false],
        ['7441001100042', 'Pan cuadrado', 'Panadería', '1450.00', '1100.00', 12, true, false],
        ['7441001100059', 'Cerveza 350 ml', 'Licores', '900.00', '650.00', 48, false, true],
    ];

    /** Seed the same catalog in each demo negocio, each one under its own row-level security scope. */
    public function run(NegocioTransaction $transaction): void
    {
        foreach ([NegociosDemoSeeder::TERESITA_ID, NegociosDemoSeeder::LA_ESQUINA_ID] as $negocioId) {
            $transaction->run($negocioId, function () use ($negocioId): void {
                foreach (self::PRODUCTS as [$code, $name, $category, $price, $cost, $stock, $perishable, $liquor]) {
                    ProductModel::query()->updateOrCreate(
                        ['negocio_id' => $negocioId, 'codigo' => $code],
                        [
                            'nombre' => $name,
                            'categoria' => $category,
                            'precio_venta' => $price,
                            'costo_actual' => $cost,
                            'stock' => $stock,
                            'es_perecedero' => $perishable,
                            'es_licor' => $liquor,
                        ],
                    );
                }
            });
        }
    }
}
