<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Src\Credit\Customers\Infrastructure\Persistence\Models\CustomerModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Src\Shared\Infrastructure\Tenancy\NegocioTransaction;

/**
 * Fictitious customers. The balance is never set here: it starts at zero and only the movements of fiado change it.
 *
 * @extends Factory<CustomerModel>
 */
final class CustomerFactory extends Factory
{
    protected $model = CustomerModel::class;

    /**
     * Default state of a fictitious active customer of a new negocio, with the base limit.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'negocio_id' => NegocioModel::factory(),
            'nombre' => fake()->randomElement(['Doña María', 'Don José', 'Ana', 'Luis', 'Carmen', 'Jorge']).' '.fake()->unique()->numberBetween(1, 99999),
            'telefono' => '8'.fake()->numerify('###-####'),
            'limite_fiado' => '90000.00',
            'activo' => true,
        ];
    }

    /** A customer who can no longer buy on fiado. */
    public function inactive(): static
    {
        return $this->state(['activo' => false]);
    }

    /** Scope the open transaction to the negocio of each row before inserting it, as its policy demands. */
    public function configure(): static
    {
        return $this->afterMaking(function (Model $model): void {
            app(NegocioTransaction::class)->scope((string) $model->getAttribute('negocio_id'));
        });
    }
}
