<?php

declare(strict_types=1);

namespace Tests\Feature\Identity\Staff;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class ManageStaffTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private NegocioModel $negocio;

    private UserModel $administrator;

    /** An administrator of a negocio is logged in. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->negocio = NegocioModel::factory()->create();
        $this->administrator = UserModel::factory()->role(Role::Administrador)->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Admin']);
        Sanctum::actingAs($this->administrator, Role::Administrador->abilities());
    }

    /** The administrator opens a cashier, who logs in with the temporary password and must change it. */
    public function test_administrator_opens_an_account_of_its_staff(): void
    {
        $this->postJson('/api/v1/personal', [
            'nombre' => 'Caja Nueva',
            'correo' => 'caja.nueva@teresita.test',
            'contrasena_temporal' => 'Temporal-2026',
            'rol' => 'cajero',
        ])
            ->assertCreated()
            ->assertJsonPath('data.rol', 'cajero')
            ->assertJsonPath('data.permisos', ['caja'])
            ->assertJsonPath('data.debe_cambiar_contrasena', true);

        $this->assertDatabaseHas('users', ['correo' => 'caja.nueva@teresita.test', 'negocio_id' => $this->negocio->id, 'rol' => 'cajero']);
        $this->postJson('/api/v1/tokens', ['correo' => 'caja.nueva@teresita.test', 'contrasena' => 'Temporal-2026', 'dispositivo' => 'Caja'])
            ->assertCreated()
            ->assertJsonPath('data.debe_cambiar_contrasena', true);
    }

    /** An administrator can open another administrator for its negocio. */
    public function test_administrator_opens_another_administrator(): void
    {
        $this->postJson('/api/v1/personal', [
            'nombre' => 'Socia',
            'correo' => 'socia@teresita.test',
            'contrasena_temporal' => 'Temporal-2026',
            'rol' => 'administrador',
        ])->assertCreated()->assertJsonPath('data.permisos', ['administrar', 'caja', 'inventario']);
    }

    /** The list has only the staff of its own negocio, without clientes. */
    public function test_the_staff_list_has_only_its_negocio_and_no_clientes(): void
    {
        UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Beto']);
        UserModel::factory()->role(Role::Cliente)->create(['negocio_id' => $this->negocio->id]);
        UserModel::factory()->role(Role::Cajero)->create();

        $this->getJson('/api/v1/personal')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.nombre', 'Admin')
            ->assertJsonPath('data.1.nombre', 'Beto');
    }

    /** Changing the role or turning an account off closes its sessions; it cannot log in while it is off. */
    public function test_changing_the_access_closes_the_sessions(): void
    {
        $cashier = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id, 'password' => Hash::make('password')]);
        $cashier->createToken('Caja', ['caja']);

        $this->patchJson("/api/v1/personal/{$cashier->id}", ['rol' => 'supervisor'])
            ->assertOk()
            ->assertJsonPath('data.permisos', ['caja', 'inventario']);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $cashier->id]);

        $this->patchJson("/api/v1/personal/{$cashier->id}", ['activo' => false])->assertOk()->assertJsonPath('data.activo', false);
        $this->postJson('/api/v1/tokens', ['correo' => $cashier->correo, 'contrasena' => 'password', 'dispositivo' => 'Caja'])->assertUnauthorized();
    }

    /** An administrator cannot change its own access, and the negocio keeps at least one active administrator. */
    public function test_the_negocio_never_loses_its_last_administrator(): void
    {
        $this->patchJson("/api/v1/personal/{$this->administrator->id}", ['activo' => false])
            ->assertConflict()
            ->assertJsonPath('detail', 'No puede cambiar el acceso de su propia cuenta. Pídaselo a otro administrador.');

        $other = UserModel::factory()->role(Role::Administrador)->create(['negocio_id' => $this->negocio->id]);
        $this->patchJson("/api/v1/personal/{$other->id}", ['rol' => 'cajero'])->assertOk();

        $this->administrator->update(['activo' => false]);
        $last = UserModel::factory()->role(Role::Administrador)->create(['negocio_id' => $this->negocio->id]);
        $this->patchJson("/api/v1/personal/{$last->id}", ['activo' => false])
            ->assertConflict()
            ->assertJsonPath('detail', 'El negocio tiene que quedar con al menos un administrador activo.');
    }

    /** A temporary password for somebody who forgot theirs: it must be changed and the sessions are closed. */
    public function test_administrator_gives_a_temporary_password(): void
    {
        $keeper = UserModel::factory()->role(Role::Bodeguero)->create(['negocio_id' => $this->negocio->id]);
        $keeper->createToken('Bodega', ['inventario']);

        $this->postJson("/api/v1/personal/{$keeper->id}/contrasena-temporal", ['contrasena_temporal' => 'Nueva-Temporal-1'])->assertNoContent();

        $keeper->refresh();
        $this->assertTrue($keeper->debe_cambiar_contrasena);
        $this->assertTrue(Hash::check('Nueva-Temporal-1', $keeper->password));
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $keeper->id]);
    }

    /** The staff of another negocio, a cliente and an unknown role are out of reach. */
    public function test_accounts_outside_the_staff_of_the_negocio_are_not_found(): void
    {
        $foreign = UserModel::factory()->role(Role::Cajero)->create();
        $cliente = UserModel::factory()->role(Role::Cliente)->create(['negocio_id' => $this->negocio->id]);

        $this->patchJson("/api/v1/personal/{$foreign->id}", ['activo' => false])->assertNotFound();
        $this->patchJson("/api/v1/personal/{$cliente->id}", ['rol' => 'administrador'])->assertNotFound();
        $this->postJson('/api/v1/personal/'.Str::uuid7()->toString().'/contrasena-temporal', ['contrasena_temporal' => 'Temporal-2026'])->assertNotFound();
        $this->postJson('/api/v1/personal', ['nombre' => 'X', 'correo' => 'x@teresita.test', 'contrasena_temporal' => 'Temporal-2026', 'rol' => 'superadmin'])
            ->assertUnprocessable();
        $this->assertTrue($foreign->refresh()->activo);
    }
}
