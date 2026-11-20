<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;

final class PlataformaDemoSeeder extends Seeder
{
    /**
     * The superadmins of the platform for the demo: one account for each developer, never a shared one. In the
     * server they are created with `php artisan plataforma:superadmin`, with a password nobody else knows.
     *
     * @var list<array{string, string}>
     */
    public const array SUPERADMINS = [
        ['jania@tuminisuper.test', 'Jania'],
        ['karol@tuminisuper.test', 'Karol'],
    ];

    /** Seed the superadmins with the documented demo password, for local development and the class demo only. */
    public function run(): void
    {
        $password = Hash::make(NegociosDemoSeeder::PASSWORD);

        foreach (self::SUPERADMINS as [$email, $name]) {
            UserModel::query()->updateOrCreate(
                ['correo' => $email],
                ['negocio_id' => null, 'nombre' => $name, 'password' => $password, 'rol' => Role::SuperAdmin->value, 'activo' => true],
            );
        }
    }
}
