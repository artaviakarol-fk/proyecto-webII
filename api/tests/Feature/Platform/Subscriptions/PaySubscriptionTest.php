<?php

declare(strict_types=1);

namespace Tests\Feature\Platform\Subscriptions;

use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionPaymentStatus;

final class PaySubscriptionTest extends SubscriptionTestCase
{
    private const string ENDPOINT = '/api/v1/suscripcion/pagos';

    /** Paid with ONVO before the date: the months are added after the date it was already paid until. */
    public function test_an_approved_card_payment_adds_the_months(): void
    {
        $this->expiresIn('+5 days');
        $this->actAs(Role::Administrador);

        $this->postJson(self::ENDPOINT, ['meses' => 2, 'pasarela' => 'onvo', 'metodo_pago' => 'tarjeta', 'metodo_proveedor_id' => 'pm_1'])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'aprobado')
            ->assertJsonPath('data.monto', 30000)
            ->assertJsonPath('data.meses', 2);

        $this->assertSame('2027-01-24 10:00:00', $this->paidUntil());
        $this->getJson('/api/v1/suscripcion')->assertJsonPath('data.estado', 'activo')->assertJsonPath('data.pagos.0.estado', 'aprobado');
    }

    /** Paid after the date: the months count from the payment, not from the old date. */
    public function test_a_late_payment_counts_from_the_day_it_was_paid(): void
    {
        $this->expiresIn('-10 days');
        $this->actAs(Role::Administrador);

        $this->postJson(self::ENDPOINT, ['pasarela' => 'onvo', 'metodo_pago' => 'tarjeta', 'metodo_proveedor_id' => 'pm_1'])->assertCreated();

        $this->assertSame('2026-12-19 10:00:00', $this->paidUntil());
    }

    /** A rejected card adds nothing. */
    public function test_a_rejected_payment_adds_nothing(): void
    {
        $this->charger->charges = SubscriptionPaymentStatus::Rejected;
        $this->actAs(Role::Administrador);
        $before = $this->paidUntil();

        $this->postJson(self::ENDPOINT, ['pasarela' => 'onvo', 'metodo_pago' => 'tarjeta', 'metodo_proveedor_id' => 'pm_1'])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'rechazado');

        $this->assertSame($before, $this->paidUntil());
    }

    /** PayPal waits for the approval; following the payment adds the months once, however many times it is asked. */
    public function test_a_pending_paypal_payment_is_followed_until_it_is_paid(): void
    {
        $this->charger->charges = SubscriptionPaymentStatus::Pending;
        $this->actAs(Role::Administrador);

        $payment = $this->postJson(self::ENDPOINT, ['pasarela' => 'paypal', 'metodo_pago' => 'tarjeta'])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'pendiente');
        $this->assertStringStartsWith('https://paypal.test/aprobar/', (string) $payment->json('data.url_aprobacion'));
        $this->assertSame('2026-12-19 10:00:00', $this->paidUntil());

        $this->getJson(self::ENDPOINT.'/'.$payment->json('data.id'))->assertOk()->assertJsonPath('data.estado', 'aprobado');
        $this->getJson(self::ENDPOINT.'/'.$payment->json('data.id'))->assertOk()->assertJsonPath('data.estado', 'aprobado');

        $this->assertSame('2027-01-19 10:00:00', $this->paidUntil());
    }

    /** A SINPE Móvil through ONVO waits and says where to send the money. */
    public function test_a_sinpe_payment_says_where_to_send_it(): void
    {
        config(['payments.sinpe_number' => '8888-8888']);
        $this->charger->charges = SubscriptionPaymentStatus::Pending;
        $this->actAs(Role::Administrador);

        $this->postJson(self::ENDPOINT, ['pasarela' => 'onvo', 'metodo_pago' => 'sinpe_movil', 'metodo_proveedor_id' => 'pm_1'])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.instrucciones.numero_sinpe', '8888-8888')
            ->assertJsonPath('data.instrucciones.monto', 15000);
    }

    /** The webhook is only a hint: the state is asked, and the same notification twice adds the months once. */
    public function test_the_webhook_adds_the_months_once(): void
    {
        $this->charger->charges = SubscriptionPaymentStatus::Pending;
        $this->actAs(Role::Administrador);
        $payment = $this->postJson(self::ENDPOINT, ['pasarela' => 'paypal', 'metodo_pago' => 'tarjeta'])->assertCreated();
        $body = ['referencia' => 'ref-'.$payment->json('data.id')];

        $this->postJson('/api/v1/webhooks/suscripciones/paypal', $body)->assertNoContent();
        $this->postJson('/api/v1/webhooks/suscripciones/paypal', $body)->assertNoContent();

        $this->assertSame(1, $this->charger->asked);
        $this->assertSame('2027-01-19 10:00:00', $this->paidUntil());
    }

    /** A notification the provider cannot prove is its own is refused. */
    public function test_an_unverified_webhook_is_refused(): void
    {
        $this->charger->trustsWebhooks = false;

        $this->postJson('/api/v1/webhooks/suscripciones/onvo', ['referencia' => 'x'])->assertUnauthorized();
    }

    /** Cash and SINPE to the platform are not paid from the app, and only the administration pays the rent. */
    public function test_manual_methods_and_other_roles_cannot_pay_from_the_app(): void
    {
        $this->actAs(Role::Administrador);
        $this->postJson(self::ENDPOINT, ['pasarela' => 'onvo', 'metodo_pago' => 'efectivo'])->assertUnprocessable();

        $this->actAs(Role::Cajero);
        $this->postJson(self::ENDPOINT, ['pasarela' => 'onvo', 'metodo_pago' => 'tarjeta', 'metodo_proveedor_id' => 'pm_1'])->assertForbidden();
        $this->getJson('/api/v1/suscripcion')->assertForbidden();
    }

    /** A payment of another negocio cannot be followed. */
    public function test_a_payment_of_another_negocio_is_not_found(): void
    {
        $this->charger->charges = SubscriptionPaymentStatus::Pending;
        $this->actAs(Role::Administrador);
        $payment = $this->postJson(self::ENDPOINT, ['pasarela' => 'paypal', 'metodo_pago' => 'tarjeta'])->assertCreated();

        $this->negocio = NegocioModel::factory()->create();
        $this->actAs(Role::Administrador);

        $this->getJson(self::ENDPOINT.'/'.$payment->json('data.id'))->assertNotFound();
    }

    /** A superadmin registers a SINPE to the platform number or cash; the months are added at once. */
    public function test_the_superadmin_registers_a_manual_payment(): void
    {
        $this->actAsSuperAdmin();

        $this->postJson("/api/v1/plataforma/negocios/{$this->negocio->id}/pagos", ['meses' => 3, 'metodo' => 'sinpe', 'referencia' => 'SINPE-123'])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'aprobado')
            ->assertJsonPath('data.proveedor', 'manual')
            ->assertJsonPath('data.monto', 45000);

        $this->assertSame('2027-03-19 10:00:00', $this->paidUntil());
    }

    /** Only a superadmin registers payments by hand. */
    public function test_an_administrator_cannot_register_a_manual_payment(): void
    {
        $this->actAs(Role::Administrador);

        $this->postJson("/api/v1/plataforma/negocios/{$this->negocio->id}/pagos", ['metodo' => 'efectivo'])->assertForbidden();
    }
}
