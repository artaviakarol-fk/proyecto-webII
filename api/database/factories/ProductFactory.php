<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Src\Shared\Infrastructure\Tenancy\NegocioTransaction;

/**
 * @extends Factory<ProductModel>
 */
final class ProductFactory extends Factory
{
    protected $model = ProductModel::class;

    /**
     * Default state of a fictitious product of a new negocio.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'negocio_id' => NegocioModel::factory(),
            'codigo' => fake()->unique()->ean13(),
            'nombre' => fake()->randomElement(['Arroz', 'Frijoles', 'Leche', 'Café', 'Azúcar', 'Pan']).' '.fake()->unique()->numberBetween(1, 99999),
            'categoria' => 'Abarrotes',
            'precio_venta' => '1500.00',
            'costo_actual' => '1100.00',
            'stock' => 20,
            'es_perecedero' => false,
            'es_licor' => false,
        ];
    }

    /** Scope the open transaction to the negocio of each row before inserting it, as its policy demands. */
    public function configure(): static
    {
        return $this->afterMaking(function (Model $model): void {
            app(NegocioTransaction::class)->scope((string) $model->getAttribute('negocio_id'));
        });
    }
}
