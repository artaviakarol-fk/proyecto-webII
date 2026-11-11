<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Payments;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\Factories\SaleFactory;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToNegocio;
use Tests\TestCase;

/** The notification of the provider (ONVO, simulated with the fake of the HTTP client) that confirms a payment. */
final class PaymentWebhookTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private const string INTENTS = 'api.onvopay.com/v1/payment-intents';

    private const string SECRET = 'whsec_test';

    private NegocioModel $negocio;

    private NegocioModel $otherNegocio;

    private string $saleId;

    /** A negocio with an open sale of 3000 colones that has a pending SINPE Móvil payment (intent_1). */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.default' => 'onvo',
            'services.onvo.secret' => 'onvo_test_secret_key_feature',
            'services.onvo.base_url' => 'https://api.onvopay.com/v1',
            'services.onvo.webhook_secret' => self::SECRET,
        ]);

        $this->travelTo('2026-11-10 20:00:00');
        $this->negocio = NegocioModel::factory()->create();
        $this->otherNegocio = NegocioModel::factory()->create();
        $arroz = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'codigo' => 'ARROZ-1', 'precio_venta' => '1500.00', 'stock' => 10]);
        $cajero = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id]);
        $this->saleId = SaleFactory::new()->for($this->negocio, $cajero)->line($arroz, 2)->create();

        Http::fake([
            self::INTENTS => Http::response(['id' => 'intent_1', 'status' => 'requires_payment_method'], 201),
            self::INTENTS.'/intent_1/confirm' => Http::response(['id' => 'intent_1', 'status' => 'processing', 'amount' => 300000, 'currency' => 'CRC'], 201),
        ]);

        Sanctum::actingAs($cajero, Role::Cajero->abilities());
        $this->postJson('/api/v1/ventas/'.$this->saleId.'/pago', ['metodo_pago' => 'sinpe_movil', 'metodo_proveedor_id' => 'pm_test_123'])->assertCreated();

        // From here on the requests come from the provider, with no account behind them.
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
    }

    /** A notification with the right secret brings the payment up to date with what ONVO says and closes the sale. */
    public function test_a_verified_notification_confirms_the_payment_and_closes_the_sale(): void
    {
        $this->providerSays('succeeded');

        $this->notify($this->succeeded(), self::SECRET)
            ->assertOk()
            ->assertJsonPath('estado', 'procesado');

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseHas('pagos', ['venta_id' => $this->saleId, 'estado' => 'pagado']);
        $this->assertDatabaseHas('ventas', ['id' => $this->saleId, 'estado' => 'cobrada', 'metodo_pago' => 'sinpe_movil']);
        $this->assertDatabaseHas('webhook_eventos', ['proveedor' => 'onvo', 'evento_id' => 'payment-intent.succeeded:intent_1', 'negocio_id' => $this->negocio->id]);
    }

    /** A notification without the secret, or with another one, is refused with 401 and changes nothing. */
    public function test_a_notification_that_is_not_proved_is_refused(): void
    {
        $this->providerSays('succeeded');

        $this->notify($this->succeeded(), null)
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/problem+json');

        $this->notify($this->succeeded(), 'webhook_secret_of_someone_else')->assertUnauthorized();

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseHas('pagos', ['venta_id' => $this->saleId, 'estado' => 'pendiente']);
        $this->assertDatabaseHas('ventas', ['id' => $this->saleId, 'estado' => 'abierta']);
        $this->assertDatabaseCount('webhook_eventos', 0);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'GET');
    }

    /** The same event sent again does not charge twice: the second one changes nothing and ONVO is asked only once. */
    public function test_a_repeated_event_is_processed_once(): void
    {
        $this->providerSays('succeeded');

        $this->notify($this->succeeded(), self::SECRET)->assertOk()->assertJsonPath('estado', 'procesado');
        $this->notify($this->succeeded(), self::SECRET)->assertOk()->assertJsonPath('estado', 'duplicado');

        $this->assertCount(1, Http::recorded(fn (Request $request): bool => $request->method() === 'GET'));
        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseCount('webhook_eventos', 1);
        $this->assertDatabaseCount('pagos', 1);
        $this->assertDatabaseHas('pagos', ['venta_id' => $this->saleId, 'estado' => 'pagado']);
    }

    /** The state is never taken from the notification: if ONVO still says processing, the payment stays pending. */
    public function test_the_state_comes_from_the_provider_and_not_from_the_notification(): void
    {
        $this->providerSays('processing');

        $this->notify($this->succeeded(), self::SECRET)->assertOk();

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseHas('pagos', ['venta_id' => $this->saleId, 'estado' => 'pendiente']);
        $this->assertDatabaseHas('ventas', ['id' => $this->saleId, 'estado' => 'abierta']);
    }

    /** A notification about a charge that is not a payment of the negocio answers 404 and is not kept as processed. */
    public function test_a_charge_that_is_not_a_payment_of_the_negocio_is_not_found(): void
    {
        $this->notify(['type' => 'payment-intent.succeeded', 'data' => ['id' => 'intent_unknown']], self::SECRET)
            ->assertNotFound()
            ->assertJsonPath('type', 'http://localhost/problemas/no-encontrado');

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseCount('webhook_eventos', 0);
    }

    /** The payment of one negocio cannot be reached with the URL of another one. */
    public function test_the_url_of_another_negocio_does_not_reach_the_payment(): void
    {
        $this->providerSays('succeeded');

        $this->notify($this->succeeded(), self::SECRET, $this->otherNegocio->id)->assertNotFound();

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseHas('pagos', ['venta_id' => $this->saleId, 'estado' => 'pendiente']);
    }

    /** An event that is not about a payment intent is answered 200 and ignored. */
    public function test_an_event_that_is_not_about_a_payment_is_ignored(): void
    {
        $this->notify(['type' => 'subscription.renewal.succeeded', 'data' => ['id' => 'sub_1']], self::SECRET)
            ->assertOk()
            ->assertJsonPath('estado', 'ignorado');

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseCount('webhook_eventos', 0);
    }

    /** If ONVO does not answer, the event is not kept as processed: the retry of the provider finishes the job. */
    public function test_when_the_provider_does_not_answer_the_event_can_be_retried(): void
    {
        Http::fake([self::INTENTS.'/intent_1' => Http::response(['message' => 'boom'], 500)]);

        $this->notify($this->succeeded(), self::SECRET)->assertStatus(503);

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseCount('webhook_eventos', 0);
        $this->assertDatabaseHas('pagos', ['venta_id' => $this->saleId, 'estado' => 'pendiente']);
    }

    /** The body of the notification of ONVO for a payment intent: no id of its own, only the type and the intent. */
    /** @return array<string, mixed> */
    private function succeeded(): array
    {
        return ['type' => 'payment-intent.succeeded', 'data' => ['id' => 'intent_1', 'status' => 'succeeded']];
    }

    /** ONVO answers this status when the intent is read. */
    private function providerSays(string $status): void
    {
        Http::fake([self::INTENTS.'/intent_1' => Http::response(['id' => 'intent_1', 'status' => $status, 'amount' => 300000, 'currency' => 'CRC'])]);
    }

    /**
     * Send a notification to the negocio, with the secret of the webhook when there is one.
     *
     * @param  array<string, mixed>  $body
     */
    private function notify(array $body, ?string $secret, ?string $negocioId = null): TestResponse
    {
        return $this->withHeaders($secret === null ? [] : ['X-Webhook-Secret' => $secret])
            ->postJson('/api/v1/webhooks/pagos/'.($negocioId ?? $this->negocio->id), $body);
    }
}
