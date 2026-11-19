<?php

declare(strict_types=1);

namespace Tests\Feature\Platform\Tenants;

use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class RegisterNegocioTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private const string ENDPOINT = '/api/v1/plataforma/negocios';

    /** A superadmin of the platform is logged in. */
    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(UserModel::factory()->role(Role::SuperAdmin)->create(), Role::SuperAdmin->abilities());
    }

    /** The superadmin opens a negocio with its first administrator, who must change the temporary password. */
    public function test_superadmin_opens_a_negocio_with_its_first_administrator(): void
    {
        $response = $this->postJson(self::ENDPOINT, $this->body())->assertCreated();

        $response
            ->assertJsonPath('data.nombre', 'Minisúper El Roble')
            ->assertJsonPath('data.estado', 'activo')
            ->assertJsonPath('data.administradores.0.correo', 'dueno@elroble.test')
            ->assertJsonPath('data.administradores.0.debe_cambiar_contrasena', true);
        $this->assertStringNotContainsString('Temporal-2026', $response->getContent());

        $negocioId = $response->json('data.id');
        $this->assertDatabaseHas('negocios', ['id' => $negocioId, 'nombre' => 'Minisúper El Roble', 'estado' => 'activo']);
        $this->assertDatabaseHas('users', [
            'negocio_id' => $negocioId,
            'correo' => 'dueno@elroble.test',
            'rol' => 'administrador',
            'debe_cambiar_contrasena' => true,
        ]);
    }

    /** The administrator logs in with the temporary password and the token says it must change it. */
    public function test_the_new_administrator_logs_in_with_the_temporary_password(): void
    {
        $this->postJson(self::ENDPOINT, $this->body())->assertCreated();

        $this->postJson('/api/v1/tokens', ['correo' => 'dueno@elroble.test', 'contrasena' => 'Temporal-2026', 'dispositivo' => 'Caja'])
            ->assertCreated()
            ->assertJsonPath('data.abilities', Role::Administrador->abilities())
            ->assertJsonPath('data.debe_cambiar_contrasena', true);
    }

    /** An email that already has an account cannot be the administrator, and no negocio is left half open. */
    public function test_an_email_with_an_account_is_refused_and_nothing_is_created(): void
    {
        UserModel::factory()->create(['correo' => 'dueno@elroble.test']);
        $negocios = NegocioModel::query()->count();

        $this->postJson(self::ENDPOINT, $this->body())
            ->assertConflict()
            ->assertJsonPath('detail', 'Ya existe una cuenta con ese correo.');

        $this->assertSame($negocios, NegocioModel::query()->count());
    }

    /** The name of the negocio, the administrator and a temporary password long enough are required. */
    public function test_the_negocio_and_its_administrator_are_validated(): void
    {
        $this->postJson(self::ENDPOINT, ['nombre' => '', 'administrador' => ['correo' => 'no-es-correo', 'contrasena_temporal' => 'corta']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre', 'administrador.nombre', 'administrador.correo', 'administrador.contrasena_temporal'], 'errores');
    }

    /** The administrator of a negocio cannot open negocios: only the platform does. */
    public function test_an_administrator_of_a_negocio_cannot_open_negocios(): void
    {
        Sanctum::actingAs(UserModel::factory()->role(Role::Administrador)->create(), Role::Administrador->abilities());

        $this->postJson(self::ENDPOINT, $this->body())->assertForbidden();
        $this->assertDatabaseMissing('users', ['correo' => 'dueno@elroble.test']);
    }

    /**
     * A new negocio and its administrator.
     *
     * @return array<string, mixed>
     */
    private function body(): array
    {
        return [
            'nombre' => 'Minisúper El Roble',
            'administrador' => [
                'nombre' => 'Dueño El Roble',
                'correo' => 'Dueno@ElRoble.test',
                'contrasena_temporal' => 'Temporal-2026',
            ],
        ];
    }
}
