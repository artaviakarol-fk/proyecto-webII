<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;

/**
 * @extends Factory<UserModel>
 */
final class UserFactory extends Factory
{
    public const string PASSWORD = 'password';

    protected $model = UserModel::class;

    private static ?string $passwordHash = null;

    /**
     * Default state of a fictitious active administrator of a new negocio.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'negocio_id' => NegocioModel::factory(),
            'nombre' => fake()->name(),
            'correo' => fake()->unique()->safeEmail(),
            'password' => self::$passwordHash ??= Hash::make(self::PASSWORD),
            'rol' => Role::Administrador->value,
            'activo' => true,
        ];
    }

    /** Give the account a role. */
    public function role(Role $role): static
    {
        return $this->state(['rol' => $role->value]);
    }

    /** Deactivate the account. */
    public function inactive(): static
    {
        return $this->state(['activo' => false]);
    }
}
