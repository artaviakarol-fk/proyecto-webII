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

/** The buyer chooses the gateway of each payment (ONVO or PayPal) without touching the .env. */
final class GatewayChoiceTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private const string INTENTS = 'api.onvopay.com/v1/payment-intents';

    private NegocioModel $negocio;

    private UserModel $cajero;

    private string $saleId;

    /** A negocio with a cashier and an open sale of 3000 colones; ONVO is the configured gateway and both have test credentials. */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.default' => 'onvo',
            'services.onvo.secret' => 'onvo_test_secret_key_feature',
            'services.onvo.publishable' => 'onvo_test_publishable_key_feature',
            'services.onvo.base_url' => 'https://api.onvopay.com/v1',
            'services.onvo.webhook_secret' => 'whsec_test',
            'services.paypal.client_id' => 'client',
            'services.paypal.client_secret' => 'secret',
            'services.paypal.base_url' => 'https://api-m.sandbox.paypal.com',
            'services.paypal.colones_per_dollar' => 500.0,
            'services.paypal.return_url' => 'https://app.test/pago/ok',
        ]);

        $this->negocio = NegocioModel::factory()->create();
        $arroz = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'codigo' => 'ARROZ-1', 'precio_venta' => '1500.00', 'stock' => 10]);
        $this->cajero = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id]);
        $this->saleId = SaleFactory::new()->for($this->negocio, $this->cajero)->line($arroz, 2)->create();
        Sanctum::actingAs($this->cajero, Role::Cajero->abilities());
    }

    /** The client learns which gateways exist, what each one offers and which one is the default; no secret is shown. */
    public function test_the_options_list_both_gateways_with_their_methods(): void
    {
        $response = $this->getJson('/api/v1/pagos/pasarelas')->assertOk();

        $response
            ->assertJsonPath('data.0.proveedor', 'onvo')
            ->assertJsonPath('data.0.metodos', ['tarjeta', 'sinpe_movil'])
            ->assertJsonPath('data.0.requiere_metodo_cliente', true)
            ->assertJsonPath('data.0.llave_publica', 'onvo_test_publishable_key_feature')
            ->assertJsonPath('data.0.configurada', true)
            ->assertJsonPath('data.0.predeterminada', true)
            ->assertJsonPath('data.1.proveedor', 'paypal')
            ->assertJsonPath('data.1.metodos', ['tarjeta'])
            ->assertJsonPath('data.1.requiere_metodo_cliente', false)
            ->assertJsonPath('data.1.llave_publica', null)
            ->assertJsonPath('data.1.predeterminada', false);

        $this->assertStringNotContainsString('onvo_test_secret_key_feature', $response->getContent());
        $this->assertStringNotContainsString('whsec_test', $response->getContent());
    }

    /** A gateway without credentials is reported as not configured, so the screen can hide it. */
    public function test_a_gateway_without_credentials_is_reported_as_not_configured(): void
    {
        config(['services.paypal.client_id' => '', 'services.paypal.client_secret' => '']);

        $this->getJson('/api/v1/pagos/pasarelas')->assertOk()->assertJsonPath('data.1.configurada', false);
    }

    /** The warehouse has nothing to do with payments. */
    public function test_the_warehouse_does_not_see_the_options(): void
    {
        Sanctum::actingAs(UserModel::factory()->role(Role::Bodeguero)->create(['negocio_id' => $this->negocio->id]), Role::Bodeguero->abilities());

        $this->getJson('/api/v1/pagos/pasarelas')->assertForbidden();
    }

    /** With ONVO configured, asking for PayPal in the body charges this payment with PayPal and stores it. */
    public function test_the_gateway_chosen_in_the_request_charges_the_payment(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'access_token_1']),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response(['id' => 'ORDER1', 'status' => 'PAYER_ACTION_REQUIRED', 'links' => [['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER1']]], 201),
        ]);

        $this->postJson($this->endpoint(), ['metodo_pago' => 'tarjeta', 'pasarela' => 'paypal'])
            ->assertCreated()
            ->assertJsonPath('data.proveedor', 'paypal')
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.referencia', 'ORDER1');

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'onvopay.com'));
        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseHas('pagos', ['venta_id' => $this->saleId, 'proveedor' => 'paypal']);
    }

    /** Naming ONVO explicitly works too, and it still asks the client for the payment method it created. */
    public function test_choosing_onvo_still_requires_the_payment_method_of_the_client(): void
    {
        Http::fake();

        $this->postJson($this->endpoint(), ['metodo_pago' => 'tarjeta', 'pasarela' => 'onvo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('metodo_proveedor_id', 'errores');

        Http::assertNothingSent();
    }

    /** With PayPal configured, the buyer can still choose ONVO for one payment, and the requirement follows the chosen gateway. */
    public function test_the_choice_wins_over_the_configured_gateway(): void
    {
        config(['payments.default' => 'paypal']);
        Http::fake([
            self::INTENTS => Http::response(['id' => 'intent_1', 'status' => 'requires_payment_method'], 201),
            self::INTENTS.'/intent_1/confirm' => Http::response(['id' => 'intent_1', 'status' => 'succeeded', 'amount' => 300000, 'currency' => 'CRC'], 201),
        ]);

        $this->postJson($this->endpoint(), ['metodo_pago' => 'tarjeta', 'pasarela' => 'onvo', 'metodo_proveedor_id' => 'pm_test_123'])
            ->assertCreated()
            ->assertJsonPath('data.proveedor', 'onvo')
            ->assertJsonPath('data.estado', 'pagado');
    }

    /** A gateway that does not exist is a validation error, and nothing is stored. */
    public function test_an_unknown_gateway_is_refused(): void
    {
        Http::fake();

        $this->postJson($this->endpoint(), ['metodo_pago' => 'tarjeta', 'pasarela' => 'stripe'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pasarela', 'errores');

        Http::assertNothingSent();
        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseCount('pagos', 0);
    }

    /** SINPE Móvil is refused when the chosen gateway is PayPal, even though the configured one (ONVO) has it. */
    public function test_the_methods_follow_the_chosen_gateway(): void
    {
        Http::fake();

        $this->postJson($this->endpoint(), ['metodo_pago' => 'sinpe_movil', 'pasarela' => 'paypal'])
            ->assertUnprocessable()
            ->assertJsonPath('detail', 'La pasarela paypal no admite el método de pago sinpe_movil.');

        Http::assertNothingSent();
    }

    /** A pending PayPal payment is followed with PayPal even though ONVO is the configured gateway. */
    public function test_a_pending_payment_is_followed_with_the_gateway_that_charged_it(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'access_token_1']),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response(['id' => 'ORDER1', 'status' => 'PAYER_ACTION_REQUIRED', 'links' => [['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER1']]], 201),
        ]);
        $this->postJson($this->endpoint(), ['metodo_pago' => 'tarjeta', 'pasarela' => 'paypal'])->assertCreated();

        Http::fake([
            'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER1/capture' => Http::response(['id' => 'ORDER1', 'status' => 'COMPLETED', 'purchase_units' => [['payments' => ['captures' => [['status' => 'COMPLETED']]]]]], 201),
            'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER1' => Http::response(['id' => 'ORDER1', 'status' => 'APPROVED']),
        ]);

        $this->getJson($this->endpoint())->assertOk()->assertJsonPath('data.proveedor', 'paypal')->assertJsonPath('data.estado', 'pagado');
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'onvopay.com'));
    }

    /** The route of the payment of the sale. */
    private function endpoint(): string
    {
        return '/api/v1/ventas/'.$this->saleId.'/pago';
    }
}
