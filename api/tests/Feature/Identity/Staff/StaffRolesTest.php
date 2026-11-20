<?php

declare(strict_types=1);

namespace Tests\Feature\Identity\Staff;

use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToNegocio;
use Tests\TestCase;

final class StaffRolesTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private NegocioModel $negocio;

    private UserModel $administrator;

    /** An administrator of a negocio is logged in. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->negocio = NegocioModel::factory()->create();
        $this->administrator = UserModel::factory()->role(Role::Administrador)->create(['negocio_id' => $this->negocio->id]);
        Sanctum::actingAs($this->administrator, Role::Administrador->abilities());
    }

    /** Every negocio starts with the four default roles, which cannot be changed. */
    public function test_the_default_roles_are_listed_first(): void
    {
        $this->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.id', 'administrador')
            ->assertJsonPath('data.0.en_uso', 1)
            ->assertJsonPath('data.3.id', 'supervisor')
            ->assertJsonPath('data.3.permisos', ['caja', 'inventario'])
            ->assertJsonPath('data.3.predeterminado', true);
    }

    /** The administrator creates a role from the permissions that exist; repeated ones count once. */
    public function test_administrator_creates_a_role_of_its_negocio(): void
    {
        $this->postJson('/api/v1/roles', ['nombre' => 'Encargado de turno', 'permisos' => ['inventario', 'caja', 'caja']])
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Encargado de turno')
            ->assertJsonPath('data.permisos', ['caja', 'inventario'])
            ->assertJsonPath('data.predeterminado', false);

        $this->getJson('/api/v1/roles')->assertJsonCount(5, 'data')->assertJsonPath('data.4.nombre', 'Encargado de turno');
    }

    /** A role needs a free name that is not a default one, and permissions that exist. */
    public function test_a_role_is_validated(): void
    {
        $this->postJson('/api/v1/roles', ['nombre' => 'Turno', 'permisos' => []])->assertUnprocessable();
        $this->postJson('/api/v1/roles', ['nombre' => 'Turno', 'permisos' => ['borrar-todo']])->assertUnprocessable();
        $this->postJson('/api/v1/roles', ['nombre' => 'cajero', 'permisos' => ['caja']])
            ->assertUnprocessable()
            ->assertJsonPath('detail', 'Ese nombre es de un rol que ya existe en todos los negocios. Elija otro.');

        $this->postJson('/api/v1/roles', ['nombre' => 'Turno', 'permisos' => ['caja']])->assertCreated();
        $this->postJson('/api/v1/roles', ['nombre' => 'TURNO', 'permisos' => ['inventario']])
            ->assertConflict()
            ->assertJsonPath('detail', 'El negocio ya tiene un rol con ese nombre.');
    }

    /** Somebody with a role of the negocio logs in with exactly the permissions of that role. */
    public function test_an_account_with_a_role_of_the_negocio_gets_its_permissions(): void
    {
        $role = $this->postJson('/api/v1/roles', ['nombre' => 'Encargado de turno', 'permisos' => ['caja', 'inventario']])->json('data.id');
        $this->postJson('/api/v1/personal', [
            'nombre' => 'Marta Turno',
            'correo' => 'marta@teresita.test',
            'contrasena_temporal' => 'Temporal-2026',
            'rol' => $role,
        ])->assertCreated()->assertJsonPath('data.rol_nombre', 'Encargado de turno');

        $this->postJson('/api/v1/tokens', ['correo' => 'marta@teresita.test', 'contrasena' => 'Temporal-2026', 'dispositivo' => 'Caja'])
            ->assertCreated()
            ->assertJsonPath('data.abilities', ['caja', 'inventario']);
    }

    /** A role that somebody has cannot be deleted; a free one can. */
    public function test_a_role_in_use_cannot_be_deleted(): void
    {
        $role = $this->postJson('/api/v1/roles', ['nombre' => 'Turno', 'permisos' => ['caja']])->json('data.id');
        $person = $this->postJson('/api/v1/personal', ['nombre' => 'Ana', 'correo' => 'ana@teresita.test', 'contrasena_temporal' => 'Temporal-2026', 'rol' => $role])->json('data.id');

        $this->deleteJson("/api/v1/roles/{$role}")->assertConflict();

        $this->patchJson("/api/v1/personal/{$person}", ['rol' => 'cajero'])->assertOk();
        $this->deleteJson("/api/v1/roles/{$role}")->assertNoContent();
    }

    /** Changing the permissions of a role closes the sessions of whoever has it, so they log in with the new ones. */
    public function test_changing_a_role_closes_the_sessions_of_its_staff(): void
    {
        $role = $this->postJson('/api/v1/roles', ['nombre' => 'Turno', 'permisos' => ['caja']])->json('data.id');
        $person = UserModel::factory()->create([
            'negocio_id' => $this->negocio->id,
            'rol' => 'personalizado',
            'rol_id' => $role,
            'password' => Hash::make('password'),
        ]);
        $person->createToken('Caja', ['caja']);

        $this->putJson("/api/v1/roles/{$role}", ['nombre' => 'Turno', 'permisos' => ['caja', 'inventario']])
            ->assertOk()
            ->assertJsonPath('data.en_uso', 1);

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $person->id]);
    }

    /** The roles of another negocio are not seen, changed or given. */
    public function test_the_roles_of_another_negocio_are_out_of_reach(): void
    {
        $other = NegocioModel::factory()->create();
        Sanctum::actingAs(UserModel::factory()->role(Role::Administrador)->create(['negocio_id' => $other->id]), Role::Administrador->abilities());
        $foreign = $this->postJson('/api/v1/roles', ['nombre' => 'Rol de La Esquina', 'permisos' => ['caja']])->json('data.id');
        Sanctum::actingAs($this->administrator, Role::Administrador->abilities());

        $this->getJson('/api/v1/roles')->assertJsonCount(4, 'data');
        $this->putJson("/api/v1/roles/{$foreign}", ['nombre' => 'Mío', 'permisos' => ['caja']])->assertNotFound();
        $this->deleteJson("/api/v1/roles/{$foreign}")->assertNotFound();
        $this->postJson('/api/v1/personal', ['nombre' => 'Ana', 'correo' => 'ana@teresita.test', 'contrasena_temporal' => 'Temporal-2026', 'rol' => $foreign])
            ->assertUnprocessable();
    }

    /** Taking the permission to administrate from the only administrators of the negocio is refused. */
    public function test_the_last_administrators_keep_the_permission_to_administrate(): void
    {
        $role = $this->postJson('/api/v1/roles', ['nombre' => 'Gerencia', 'permisos' => ['administrar', 'caja']])->json('data.id');
        UserModel::factory()->create(['negocio_id' => $this->negocio->id, 'rol' => 'personalizado', 'rol_id' => $role]);
        $this->administrator->update(['activo' => false]);

        $this->putJson("/api/v1/roles/{$role}", ['nombre' => 'Gerencia', 'permisos' => ['caja']])
            ->assertConflict()
            ->assertJsonPath('detail', 'El negocio tiene que quedar con al menos un administrador activo.');
    }
}
