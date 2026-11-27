<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Shared\Domain\ImageFolders;
use Src\Shared\Domain\ValueObjects\NegocioId;
use Src\Shared\Infrastructure\Storage\DiskImageStorage;
use Src\Shared\Infrastructure\Tenancy\NegocioTransaction;

final class ProductosDemoSeeder extends Seeder
{
    /**
     * Fictitious products: code, name, category, sale price, cost, stock, perishable, liquor and units sold per day.
     *
     * The stocks are on purpose uneven, so the restock report has something to say: some products are out of stock,
     * some are about to run out and some are fine. The pace is what VentasDemoSeeder sells of each one; the stock is not
     * taken from those sales.
     *
     * @var list<array{string, string, string, string, string, int, bool, bool, float}>
     */
    public const array PRODUCTS = [
        ['7441001100011', 'Arroz 1 kg', 'Abarrotes', '1350.00', '1050.00', 40, false, false, 2.5],
        ['7441001100028', 'Frijoles negros 900 g', 'Abarrotes', '1600.00', '1250.00', 25, false, false, 1.8],
        ['7441001100035', 'Leche entera 1 L', 'Lácteos', '950.00', '760.00', 30, true, false, 6.0],
        ['7441001100042', 'Pan cuadrado', 'Panadería', '1450.00', '1100.00', 12, true, false, 5.0],
        ['7441001100059', 'Cerveza 350 ml', 'Licores', '900.00', '650.00', 48, false, true, 4.0],
        ['7441001100060', 'Azúcar 1 kg', 'Abarrotes', '1100.00', '850.00', 35, false, false, 3.0],
        ['7441001100061', 'Aceite vegetal 900 ml', 'Abarrotes', '2650.00', '2100.00', 6, false, false, 1.5],
        ['7441001100062', 'Sal 500 g', 'Abarrotes', '600.00', '420.00', 50, false, false, 0.5],
        ['7441001100063', 'Atún en lata 140 g', 'Abarrotes', '1350.00', '1000.00', 0, false, false, 2.0],
        ['7441001100064', 'Pasta espagueti 400 g', 'Abarrotes', '1050.00', '780.00', 22, false, false, 1.5],
        ['7441001100065', 'Café molido 250 g', 'Abarrotes', '2850.00', '2200.00', 3, false, false, 1.2],
        ['7441001100066', 'Harina de trigo 1 kg', 'Abarrotes', '1300.00', '1000.00', 18, false, false, 0.7],
        ['7441001100067', 'Salsa de tomate 400 g', 'Abarrotes', '1200.00', '900.00', 14, false, false, 0.8],
        ['7441001100068', 'Queso blanco 500 g', 'Lácteos', '3200.00', '2500.00', 8, true, false, 1.4],
        ['7441001100069', 'Huevos cartón de 30', 'Lácteos', '5200.00', '4300.00', 4, true, false, 1.6],
        ['7441001100070', 'Mantequilla 200 g', 'Lácteos', '1900.00', '1500.00', 16, true, false, 0.6],
        ['7441001100071', 'Yogur natural 1 L', 'Lácteos', '1750.00', '1350.00', 0, true, false, 0.0],
        ['7441001100072', 'Pan de molde integral', 'Panadería', '1650.00', '1250.00', 10, true, false, 1.0],
        ['7441001100073', 'Gaseosa cola 2 L', 'Bebidas', '1700.00', '1300.00', 24, false, false, 3.5],
        ['7441001100074', 'Agua 600 ml', 'Bebidas', '700.00', '450.00', 60, false, false, 5.0],
        ['7441001100075', 'Jugo de naranja 1 L', 'Bebidas', '1500.00', '1100.00', 20, true, false, 1.2],
        ['7441001100076', 'Detergente en polvo 1 kg', 'Limpieza', '2900.00', '2300.00', 15, false, false, 0.8],
        ['7441001100077', 'Jabón en barra', 'Limpieza', '650.00', '450.00', 30, false, false, 1.0],
        ['7441001100078', 'Papel higiénico 4 rollos', 'Limpieza', '2400.00', '1850.00', 7, false, false, 1.7],
        ['7441001100079', 'Cloro 1 L', 'Limpieza', '1250.00', '900.00', 12, false, false, 0.6],
        ['7441001100080', 'Papas fritas 100 g', 'Snacks', '1100.00', '800.00', 28, false, false, 2.2],
        ['7441001100081', 'Galletas de soda', 'Snacks', '1150.00', '850.00', 5, false, false, 1.8],
        ['7441001100082', 'Chocolate en barra', 'Snacks', '900.00', '650.00', 40, false, false, 1.5],
        ['7441001100083', 'Cerveza lata 473 ml', 'Licores', '1100.00', '800.00', 36, false, true, 2.5],
        ['7441001100084', 'Ron 750 ml', 'Licores', '8900.00', '7000.00', 6, false, true, 0.3],
    ];

    /** Seed the same catalog in each demo negocio, each one under its own row-level security scope. */
    public function run(NegocioTransaction $transaction): void
    {
        foreach ([NegociosDemoSeeder::TERESITA_ID, NegociosDemoSeeder::LA_ESQUINA_ID] as $negocioId) {
            $transaction->run($negocioId, function () use ($negocioId): void {
                foreach (self::PRODUCTS as [$code, $name, $category, $price, $cost, $stock, $perishable, $liquor]) {
                    $product = ProductModel::query()->updateOrCreate(
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

                    $this->attachPhoto($product, $negocioId, $code);
                }
            });
        }
    }

    /** Give the product the demo photo that matches its code (database/seeders/images/{code}.png), if it has none yet. */
    private function attachPhoto(ProductModel $product, string $negocioId, string $code): void
    {
        $source = __DIR__.'/images/'.$code.'.png';

        if ($product->foto_path !== null || ! is_file($source)) {
            return;
        }

        $path = (new DiskImageStorage)->store(ImageFolders::product(new NegocioId($negocioId), (string) $product->id), $source, 'png');
        $product->update(['foto_path' => $path]);
    }
}
