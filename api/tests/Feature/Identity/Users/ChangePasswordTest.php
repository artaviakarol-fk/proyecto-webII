<?php

declare(strict_types=1);

namespace Tests\Feature\Identity\Users;

use Illuminate\Support\Facades\Hash;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class ChangePasswordTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private const string ENDPOINT = '/api/v1/usuario/contrasena';

    /** While the temporary password is pending, every route answers 403 except seeing who it is and logging out. */
    public function test_an_account_with_a_temporary_password_can_only_change_it(): void
    {
        $token = $this->login($this->administratorWithTemporaryPassword());

        $this->withToken($token)->getJson('/api/v1/productos')
            ->assertForbidden()
            ->assertJsonPath('type', 'http://localhost/problemas/prohibido')
            ->assertJsonPath('detail', 'Debe cambiar la contraseña temporal antes de continuar.');
        $this->withToken($token)->getJson('/api/v1/usuario')
            ->assertOk()
            ->assertJsonPath('data.debe_cambiar_contrasena', true);
    }

    /** After changing it, the same token reaches the routes of the role and the new password logs in. */
    public function test_changing_the_temporary_password_opens_the_account(): void
    {
        $user = $this->administratorWithTemporaryPassword();
        $token = $this->login($user);

        $this->withToken($token)->putJson(self::ENDPOINT, ['contrasena_actual' => 'Temporal-2026', 'contrasena_nueva' => 'MiClaveNueva!'])
            ->assertNoContent();

        // Each real request reads the account again; the test keeps one application, so it forgets the one it read.
        $this->app->make('auth')->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/productos')->assertOk();
        $this->assertFalse($user->refresh()->debe_cambiar_contrasena);
        $this->assertTrue(Hash::check('MiClaveNueva!', $user->password));
        $this->postJson('/api/v1/tokens', ['correo' => $user->correo, 'contrasena' => 'MiClaveNueva!', 'dispositivo' => 'Caja'])
            ->assertCreated()
            ->assertJsonPath('data.debe_cambiar_contrasena', false);
    }

    /** The current password is required, and the new one must be different and long enough. */
    public function test_the_current_password_is_checked_and_the_new_one_validated(): void
    {
        $token = $this->login($this->administratorWithTemporaryPassword());

        $this->withToken($token)->putJson(self::ENDPOINT, ['contrasena_actual' => 'otra-cosa', 'contrasena_nueva' => 'MiClaveNueva!'])
            ->assertUnprocessable()
            ->assertJsonPath('detail', 'La contraseña actual no es correcta.');
        $this->withToken($token)->putJson(self::ENDPOINT, ['contrasena_actual' => 'Temporal-2026', 'contrasena_nueva' => 'Temporal-2026'])
            ->assertUnprocessable()
            ->assertJsonPath('detail', 'La contraseña nueva debe ser distinta de la actual.');
        $this->withToken($token)->putJson(self::ENDPOINT, ['contrasena_actual' => 'Temporal-2026', 'contrasena_nueva' => 'corta'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contrasena_nueva', 'errores');
    }

    /** Any account can change its own password, not only those with a temporary one. */
    public function test_any_account_changes_its_own_password(): void
    {
        $cashier = UserModel::factory()->role(Role::Cajero)->create();

        $this->withToken($this->login($cashier))->putJson(self::ENDPOINT, ['contrasena_actual' => 'password', 'contrasena_nueva' => 'OtraClave2026'])
            ->assertNoContent();
        $this->assertTrue(Hash::check('OtraClave2026', $cashier->refresh()->password));
    }

    /** The administrator of a new negocio, with the temporary password the platform gave. */
    private function administratorWithTemporaryPassword(): UserModel
    {
        return UserModel::factory()->role(Role::Administrador)->create([
            'password' => Hash::make('Temporal-2026'),
            'debe_cambiar_contrasena' => true,
        ]);
    }

    /** Log in with a real token; the temporary password is the one of the factory unless it was changed. */
    private function login(UserModel $user): string
    {
        $password = $user->debe_cambiar_contrasena ? 'Temporal-2026' : 'password';

        return (string) $this->postJson('/api/v1/tokens', ['correo' => $user->correo, 'contrasena' => $password, 'dispositivo' => 'Caja'])
            ->assertCreated()
            ->json('data.token');
    }
}
