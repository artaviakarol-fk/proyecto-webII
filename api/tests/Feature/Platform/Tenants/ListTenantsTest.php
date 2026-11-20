<?php

declare(strict_types=1);

namespace Tests\Feature\Platform\Tenants;

use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class ListTenantsTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private const string ENDPOINT = '/api/v1/plataforma/negocios';

    /** A superadmin of the platform is logged in. */
    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(UserModel::factory()->role(Role::SuperAdmin)->create(), Role::SuperAdmin->abilities());
    }

    /** The superadmin sees every negocio with its administrators, and nothing of the data of the negocio. */
    public function test_superadmin_sees_every_negocio_with_its_administrators(): void
    {
        $teresita = NegocioModel::factory()->create(['nombre' => 'Minisúper Teresita']);
        $esquina = NegocioModel::factory()->create(['nombre' => 'Minisúper La Esquina']);
        UserModel::factory()->role(Role::Administrador)->create(['negocio_id' => $teresita->id, 'correo' => 'admin@teresita.test']);
        UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $teresita->id, 'correo' => 'caja@teresita.test']);
        UserModel::factory()->role(Role::Administrador)->create(['negocio_id' => $esquina->id, 'correo' => 'admin@laesquina.test']);

        $response = $this->getJson(self::ENDPOINT)->assertOk()->assertJsonPath('meta.total', 2);

        $byName = collect($response->json('data'))->keyBy('nombre');
        $this->assertSame(['admin@teresita.test'], array_column($byName['Minisúper Teresita']['administradores'], 'correo'));
        $this->assertSame(['admin@laesquina.test'], array_column($byName['Minisúper La Esquina']['administradores'], 'correo'));
        $this->assertSame(['id', 'nombre', 'estado', 'creado_el', 'administradores', 'servicio'], array_keys($response->json('data.0')));
    }

    /** The listing can look for a part of the name. */
    public function test_superadmin_looks_for_a_negocio_by_name(): void
    {
        NegocioModel::factory()->create(['nombre' => 'Minisúper Teresita']);
        NegocioModel::factory()->create(['nombre' => 'Minisúper La Esquina']);

        $this->getJson(self::ENDPOINT.'?buscar=esquina')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Minisúper La Esquina');
    }

    /** One negocio shows the same summary; one that does not exist answers 404. */
    public function test_superadmin_sees_one_negocio(): void
    {
        $negocio = NegocioModel::factory()->create(['nombre' => 'Minisúper Teresita']);

        $this->getJson(self::ENDPOINT.'/'.$negocio->id)->assertOk()->assertJsonPath('data.nombre', 'Minisúper Teresita');
        $this->getJson(self::ENDPOINT.'/'.Str::uuid7()->toString())->assertNotFound();
    }

    /** The administrator of a negocio cannot list the other negocios of the platform. */
    public function test_an_administrator_of_a_negocio_cannot_see_the_platform(): void
    {
        Sanctum::actingAs(UserModel::factory()->role(Role::Administrador)->create(), Role::Administrador->abilities());

        $this->getJson(self::ENDPOINT)->assertForbidden();
    }
}
