<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Payments;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\Factories\SaleFactory;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToNegocio;
use Tests\TestCase;

/** The payment of a sale through the API, with ONVO simulated by the fake of the HTTP client. */
final class SalePaymentTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private const string INTENTS = 'api.onvopay.com/v1/payment-intents';

    private NegocioModel $negocio;

    private NegocioModel $otherNegocio;

    private UserModel $cajero;

    private ProductModel $arroz;

    private string $saleId;

    /** Two negocios, a cashier of the first one and an open sale of 3000 colones (two kilos of rice). */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.default' => 'onvo',
            'services.onvo.secret' => 'onvo_test_secret_key_feature',
            'services.onvo.base_url' => 'https://api.onvopay.com/v1',
            'services.onvo.webhook_secret' => 'whsec_test',
        ]);

        $this->travelTo('2026-10-25 20:00:00');
        $this->negocio = NegocioModel::factory()->create();
        $this->otherNegocio = NegocioModel::factory()->create();
        $this->arroz = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'codigo' => 'ARROZ-1', 'precio_venta' => '1500.00', 'stock' => 10]);
        $this->cajero = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id]);
        $this->saleId = SaleFactory::new()->for($this->negocio, $this->cajero)->line($this->arroz, 2)->create();
        Sanctum::actingAs($this->cajero, Role::Cajero->abilities());
    }

    /** A card payment ends at once: ONVO is asked for the exact total of the sale, in céntimos, and the payment is stored. */
    public function test_a_card_payment_is_charged_for_the_total_of_the_sale(): void
    {
        $this->fakeOnvo('succeeded');

        $this->postJson($this->endpoint($this->saleId), $this->body('tarjeta'))
            ->assertCreated()
            ->assertHeader('Location', 'http://localhost'.$this->endpoint($this->saleId))
            ->assertJsonPath('data.estado', 'pagado')
            ->assertJsonPath('data.proveedor', 'onvo')
            ->assertJsonPath('data.metodo_pago', 'tarjeta')
            ->assertJsonPath('data.venta_id', $this->saleId)
            ->assertJsonPath('data.referencia', 'intent_1')
            ->assertJsonPath('data.moneda', 'CRC')
            ->assertJsonPath('data.monto', fn (mixed $amount): bool => (float) $amount === 3000.0)
            ->assertJsonPath('data.resuelto_en', '2026-10-25T20:00:00-06:00')
            ->assertJsonPath('data.instrucciones', null);

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/payment-intents') && $request->data()['amount'] === 300000);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/payment-intents/intent_1/confirm') && $request->data()['paymentMethodId'] === 'pm_test_123');

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseHas('pagos', [
            'venta_id' => $this->saleId,
            'negocio_id' => $this->negocio->id,
            'estado' => 'pagado',
            'monto' => '3000.00',
            'referencia_externa' => 'intent_1',
        ]);
    }

    /** A SINPE Móvil payment stays pending and tells the buyer where to transfer. */
    public function test_a_sinpe_payment_stays_pending_with_instructions(): void
    {
        $this->fakeOnvo('processing');

        $this->postJson($this->endpoint($this->saleId), $this->body('sinpe_movil'))
            ->assertCreated()
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.resuelto_en', null)
            ->assertJsonPath('data.instrucciones.numero_sinpe', '+50670196686')
            ->assertJsonPath('data.instrucciones.monto', fn (mixed $amount): bool => (float) $amount === 3000.0);

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseHas('pagos', ['venta_id' => $this->saleId, 'estado' => 'pendiente', 'metodo_pago' => 'sinpe_movil']);
    }

    /** Asking for the payment of a pending one reads what ONVO says now and keeps it. */
    public function test_showing_a_pending_payment_refreshes_it(): void
    {
        $this->fakeOnvo('processing');
        $this->postJson($this->endpoint($this->saleId), $this->body('sinpe_movil'))->assertCreated();

        Http::fake([self::INTENTS.'/intent_1' => Http::response(['id' => 'intent_1', 'status' => 'succeeded'])]);

        $this->getJson($this->endpoint($this->saleId))
            ->assertOk()
            ->assertJsonPath('data.estado', 'pagado')
            ->assertJsonPath('data.instrucciones', null);

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseHas('pagos', ['venta_id' => $this->saleId, 'estado' => 'pagado']);
    }

    /** A sale cannot be paid twice while its payment is pending or paid. */
    public function test_a_second_payment_is_a_conflict(): void
    {
        $this->fakeOnvo('processing');
        $this->postJson($this->endpoint($this->saleId), $this->body('sinpe_movil'))->assertCreated();

        $this->postJson($this->endpoint($this->saleId), $this->body('tarjeta'))
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'http://localhost/problemas/conflicto')
            ->assertJsonPath('detail', 'La venta ya tiene un pago pendiente o pagado.');

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseCount('pagos', 1);
    }

    /** A sale that is already paid or cancelled cannot be paid with a card. */
    public function test_a_sale_that_is_not_open_cannot_be_paid(): void
    {
        $paid = SaleFactory::new()->for($this->negocio, $this->cajero)->line($this->arroz, 1)->paid()->create();
        $cancelled = SaleFactory::new()->for($this->negocio, $this->cajero)->line($this->arroz, 1)->cancelled()->create();
        Http::fake();

        foreach ([$paid, $cancelled] as $saleId) {
            $this->postJson($this->endpoint($saleId), $this->body('tarjeta'))
                ->assertConflict()
                ->assertJsonPath('detail', 'La venta ya no está abierta, por eso no se puede pagar.');
        }

        Http::assertNothingSent();
    }

    /** A card that the bank refuses answers a problem with the code, and nothing is stored. */
    public function test_a_declined_card_answers_a_problem_and_stores_nothing(): void
    {
        Http::fake([
            self::INTENTS => Http::response(['id' => 'intent_1', 'status' => 'requires_payment_method'], 201),
            self::INTENTS.'/intent_1/confirm' => Http::response([
                'code' => 'cards.invalid_card_info',
                'details' => ['card' => ['reason' => 'issuer_declined', 'declineCode' => 'insufficient_funds']],
            ], 400),
        ]);

        $this->postJson($this->endpoint($this->saleId), $this->body('tarjeta'))
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('detail', 'El pago fue rechazado (código: insufficient_funds). Intente con otro medio de pago.');

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseCount('pagos', 0);
    }

    /** If ONVO is down the API answers 503 in RFC 9457 with Retry-After, and nothing is stored. */
    public function test_an_unavailable_provider_answers_503_with_retry_after(): void
    {
        Http::fake([self::INTENTS => Http::response(['message' => 'boom'], 500)]);

        $this->postJson($this->endpoint($this->saleId), $this->body('tarjeta'))
            ->assertStatus(503)
            ->assertHeader('Retry-After', '30')
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'http://localhost/problemas/servicio-no-disponible')
            ->assertJsonPath('status', 503);

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseCount('pagos', 0);
    }

    /** A sale of another negocio answers 404, as if it did not exist, and ONVO is never asked. */
    public function test_a_sale_of_another_negocio_is_not_found(): void
    {
        $otherCashier = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->otherNegocio->id]);
        $otherProduct = ProductModel::factory()->create(['negocio_id' => $this->otherNegocio->id, 'precio_venta' => '1000.00', 'stock' => 5]);
        $foreign = SaleFactory::new()->for($this->otherNegocio, $otherCashier)->line($otherProduct, 1)->create();
        Http::fake();

        $this->postJson($this->endpoint($foreign), $this->body('tarjeta'))->assertNotFound();
        $this->getJson($this->endpoint($foreign))->assertNotFound();
        Http::assertNothingSent();
    }

    /** The payment of a sale that has none is a 404. */
    public function test_a_sale_without_payment_is_not_found(): void
    {
        $this->getJson($this->endpoint($this->saleId))->assertNotFound();
    }

    /** The data of the payment is validated before ONVO is asked. */
    public function test_the_data_of_the_payment_is_validated(): void
    {
        Http::fake();

        $this->postJson($this->endpoint($this->saleId), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['metodo_pago', 'metodo_proveedor_id'], 'errores');
        $this->postJson($this->endpoint($this->saleId), ['metodo_pago' => 'cheque', 'metodo_proveedor_id' => 'pm test!'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['metodo_pago', 'metodo_proveedor_id'], 'errores');

        Http::assertNothingSent();
    }

    /** The cashier and the administration charge; the warehouse keeper and anonymous visitors do not. */
    public function test_only_the_register_staff_charges(): void
    {
        $this->fakeOnvo('succeeded');

        $bodeguero = UserModel::factory()->role(Role::Bodeguero)->create(['negocio_id' => $this->negocio->id]);
        Sanctum::actingAs($bodeguero, Role::Bodeguero->abilities());
        $this->postJson($this->endpoint($this->saleId), $this->body('tarjeta'))->assertForbidden();
        $this->getJson($this->endpoint($this->saleId))->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->postJson($this->endpoint($this->saleId), $this->body('tarjeta'))->assertUnauthorized();
    }

    /** The configured gateway must be one that exists: anything else is an internal error without details. */
    public function test_an_unknown_gateway_is_an_internal_error(): void
    {
        config(['payments.default' => 'unknown']);
        Http::fake();

        $this->postJson($this->endpoint($this->saleId), $this->body('tarjeta'))
            ->assertStatus(500)
            ->assertJsonPath('type', 'http://localhost/problemas/error-interno');
        Http::assertNothingSent();
    }

    /** Answer the creation of the intent and its confirmation with the given status. */
    private function fakeOnvo(string $status): void
    {
        Http::fake([
            self::INTENTS => Http::response(['id' => 'intent_1', 'status' => 'requires_payment_method'], 201),
            self::INTENTS.'/intent_1/confirm' => Http::response(['id' => 'intent_1', 'status' => $status, 'amount' => 300000, 'currency' => 'CRC'], 201),
        ]);
    }

    /** The route of the payment of a sale. */
    private function endpoint(string $saleId): string
    {
        return '/api/v1/ventas/'.$saleId.'/pago';
    }

    /**
     * The body of a payment: the way of paying and the payment method that the client created with ONVO.
     *
     * @return array<string, string>
     */
    private function body(string $method): array
    {
        return ['metodo_pago' => $method, 'metodo_proveedor_id' => 'pm_test_123'];
    }
}
