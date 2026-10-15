<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
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
}
