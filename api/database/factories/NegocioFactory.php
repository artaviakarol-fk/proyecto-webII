<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;

/**
 * @extends Factory<NegocioModel>
 */
final class NegocioFactory extends Factory
{
    protected $model = NegocioModel::class;

    /**
     * Default state of a fictitious active negocio.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Minisúper '.fake()->unique()->lastName(),
            'logo_path' => null,
            'estado' => NegocioModel::ACTIVE,
        ];
    }

    /** Every negocio is born with its rent paid for a month, so it can be used; a test changes the date when it needs. */
    public function configure(): static
    {
        return $this->afterCreating(function (NegocioModel $negocio): void {
            DB::table('suscripciones')->insertOrIgnore([
                'negocio_id' => $negocio->id,
                'vence_el' => now()->addMonth(),
                'en_prueba' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
