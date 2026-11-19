<?php

declare(strict_types=1);

namespace Tests\Feature\Platform\Subscriptions;

use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;

final class ServiceSuspensionTest extends SubscriptionTestCase
{
    private const array SCHEDULE = ['dias' => []];

    /** While the rent is paid nothing changes and there is no warning. */
    public function test_the_administrator_sees_the_rent_paid(): void
    {
        $this->actAs(Role::Administrador);

        $this->getJson('/api/v1/suscripcion')
            ->assertOk()
            ->assertJsonPath('data.estado', 'activo')
            ->assertJsonPath('data.dias_restantes', 30)
            ->assertJsonPath('data.precio_mensual', 15000)
            ->assertJsonPath('data.aviso', null);
    }

    /** Seven days before the date the warning is yellow; after the date the service still works for three days. */
    public function test_the_warning_changes_as_the_date_comes(): void
    {
        $this->actAs(Role::Administrador);

        $this->expiresIn('+5 days');
        $this->getJson('/api/v1/suscripcion')
            ->assertJsonPath('data.estado', 'por_vencer')
            ->assertJsonPath('data.aviso', 'Faltan 5 días para que venza su plan. Pague para no perder el servicio.');

        $this->expiresIn('-1 day');
        $this->getJson('/api/v1/suscripcion')
            ->assertJsonPath('data.estado', 'en_gracia')
            ->assertJsonPath('data.aviso', 'Su plan venció. Tiene 2 días de gracia para pagar antes de que se suspenda el servicio.');
        $this->getJson('/api/v1/productos')->assertOk();
    }

    /** Suspended: the staff cannot use the system, not even with a token it already had. */
    public function test_the_staff_of_a_suspended_negocio_cannot_use_it(): void
    {
        $this->expiresIn('-4 days');
        $this->actAs(Role::Cajero);

        $this->getJson('/api/v1/productos')
            ->assertForbidden()
            ->assertJsonPath('type', self::PROHIBIDO)
            ->assertJsonPath('detail', __('platform.suspended'));
        $this->getJson('/api/v1/usuario')->assertOk();
    }

    /** Suspended: the staff cannot log in, the administrator can, to see and to pay. */
    public function test_only_the_administrator_logs_in_to_a_suspended_negocio(): void
    {
        $this->expiresIn('-4 days');
        $cashier = $this->account(Role::Cajero);
        $administrator = $this->account(Role::Administrador);

        $this->login($cashier->correo)->assertForbidden()->assertJsonPath('detail', __('platform.suspended'));
        $this->login($administrator->correo)->assertCreated();
    }

    /** Suspended: the administrator sees the data and the rent, but cannot change anything. */
    public function test_the_administrator_of_a_suspended_negocio_only_reads_and_pays(): void
    {
        $this->expiresIn('-4 days');
        $this->actAs(Role::Administrador);

        $this->getJson('/api/v1/productos')->assertOk();
        $this->getJson('/api/v1/suscripcion')
            ->assertOk()
            ->assertJsonPath('data.estado', 'suspendido')
            ->assertJsonPath('data.aviso', __('platform.warning_suspended'));
        $this->putJson('/api/v1/negocio/horario', self::SCHEDULE)
            ->assertForbidden()
            ->assertJsonPath('detail', __('platform.suspended_read_only'));
        $this->postJson('/api/v1/suscripcion/pagos', ['pasarela' => 'onvo', 'metodo_pago' => 'tarjeta', 'metodo_proveedor_id' => 'pm_1'])
            ->assertCreated();
    }

    /** Suspended: nobody new can register as cliente of the negocio. */
    public function test_a_suspended_negocio_does_not_take_new_clientes(): void
    {
        $this->expiresIn('-4 days');

        $this->postJson("/api/v1/negocios/{$this->negocio->id}/registro", ['nombre' => 'Ana Rojas', 'correo' => 'ana@correo.test', 'contrasena' => 'secreto-123'])
            ->assertForbidden()
            ->assertJsonPath('detail', __('platform.suspended'));
        $this->assertDatabaseMissing('users', ['correo' => 'ana@correo.test']);
    }

    /** A negocio turned off by the platform is suspended even with the rent paid, and comes back when turned on. */
    public function test_the_platform_turns_a_negocio_off_and_on(): void
    {
        $this->actAsSuperAdmin();
        $this->patchJson("/api/v1/plataforma/negocios/{$this->negocio->id}", ['estado' => 'inactivo'])
            ->assertOk()
            ->assertJsonPath('data.estado', 'inactivo')
            ->assertJsonPath('data.servicio.estado', 'suspendido');
        $cashier = $this->account(Role::Cajero);
        $this->login($cashier->correo)->assertForbidden();

        $this->actAsSuperAdmin();
        $this->patchJson("/api/v1/plataforma/negocios/{$this->negocio->id}", ['estado' => 'activo'])
            ->assertOk()
            ->assertJsonPath('data.servicio.estado', 'activo');
        $this->login($cashier->correo)->assertCreated();
        $this->assertSame(NegocioModel::ACTIVE, DB::table('negocios')->where('id', $this->negocio->id)->value('estado'));
    }

    /** A negocio that opens on the platform starts with five days of free trial. */
    public function test_a_new_negocio_starts_with_the_free_trial(): void
    {
        $this->actAsSuperAdmin();

        $this->postJson('/api/v1/plataforma/negocios', [
            'nombre' => 'Minisúper El Roble',
            'administrador' => ['nombre' => 'Dueño', 'correo' => 'dueno@elroble.test', 'contrasena_temporal' => 'Temporal-2026'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.servicio.en_prueba', true)
            ->assertJsonPath('data.servicio.dias_restantes', 5)
            ->assertJsonPath('data.servicio.estado', 'por_vencer');
    }

    /** Ask for a token with the password of the factory. */
    private function login(string $email): TestResponse
    {
        $this->app->make('auth')->forgetGuards();

        return $this->postJson('/api/v1/tokens', ['correo' => $email, 'contrasena' => UserFactory::PASSWORD, 'dispositivo' => 'Caja']);
    }
}
