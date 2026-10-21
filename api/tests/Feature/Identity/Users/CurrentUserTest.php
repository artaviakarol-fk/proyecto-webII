<?php

declare(strict_types=1);

namespace Tests\Feature\Identity\Users;

use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class CurrentUserTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private const string ENDPOINT = '/api/v1/usuario';

    /** Each role sees who is logged in, its role and what the role allows, without the password. */
    #[DataProvider('roles')]
    public function test_shows_the_account_behind_the_token(Role $role, array $abilities): void
    {
        $user = UserModel::factory()->role($role)->create(['nombre' => 'Ana Rojas', 'correo' => 'ana@minisuper.test']);
        Sanctum::actingAs($user, $role->abilities());

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $user->id,
                'nombre' => 'Ana Rojas',
                'correo' => 'ana@minisuper.test',
                'rol' => $role->value,
                'abilities' => $abilities,
                'negocio_id' => $user->negocio_id,
            ]]);
    }

    /**
     * Roles and the abilities they carry.
     *
     * @return array<string, array{Role, list<string>}>
     */
    public static function roles(): array
    {
        return [
            'administrador' => [Role::Administrador, ['administrar', 'caja', 'inventario']],
            'cajero' => [Role::Cajero, ['caja']],
            'bodeguero' => [Role::Bodeguero, ['inventario']],
        ];
    }

    /** Without a token there is nobody to show. */
    public function test_requires_a_token(): void
    {
        $this->getJson(self::ENDPOINT)
            ->assertUnauthorized()
            ->assertJsonPath('type', 'http://localhost/problemas/no-autenticado');
    }

    /** An account deactivated after logging in is refused, even with a token it still holds. */
    public function test_deactivated_account_is_refused(): void
    {
        $user = UserModel::factory()->role(Role::Cajero)->inactive()->create();
        Sanctum::actingAs($user, Role::Cajero->abilities());

        $this->getJson(self::ENDPOINT)->assertUnauthorized();
    }
}
