<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;

final class NegociosDemoSeeder extends Seeder
{
    public const string PASSWORD = 'password';

    public const string TERESITA_ID = '019a0000-0000-7000-8000-000000000001';

    public const string LA_ESQUINA_ID = '019a0000-0000-7000-8000-000000000002';

    /**
     * Two fictitious negocios and their staff: negocio id, negocio name and accounts (email, name, role).
     *
     * @var list<array{string, string, list<array{string, string, Role}>}>
     */
    private const array NEGOCIOS = [
        [self::TERESITA_ID, 'Minisúper Teresita', [
            ['admin@teresita.test', 'Administración Teresita', Role::Administrador],
            ['caja@teresita.test', 'Caja Teresita', Role::Cajero],
            ['bodega@teresita.test', 'Bodega Teresita', Role::Bodeguero],
        ]],
        [self::LA_ESQUINA_ID, 'Minisúper La Esquina', [
            ['admin@laesquina.test', 'Administración La Esquina', Role::Administrador],
            ['caja@laesquina.test', 'Caja La Esquina', Role::Cajero],
        ]],
    ];

    /** Seed the demo negocios with fictitious accounts, for local development and the class demo only. */
    public function run(): void
    {
        $password = Hash::make(self::PASSWORD);

        foreach (self::NEGOCIOS as [$id, $name, $accounts]) {
            NegocioModel::query()->updateOrCreate(['id' => $id], ['nombre' => $name, 'estado' => NegocioModel::ACTIVE]);

            foreach ($accounts as [$email, $accountName, $role]) {
                UserModel::query()->updateOrCreate(
                    ['correo' => $email],
                    ['negocio_id' => $id, 'nombre' => $accountName, 'password' => $password, 'rol' => $role->value, 'activo' => true],
                );
            }
        }
    }
}
