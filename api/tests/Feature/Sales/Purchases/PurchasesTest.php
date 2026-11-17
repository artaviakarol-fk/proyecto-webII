<?php

declare(strict_types=1);

namespace Tests\Feature\Sales\Purchases;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToNegocio;
use Tests\TestCase;

/**
 * The cliente reserves, then buys what it holds and chooses the gateway of the payment (ONVO or PayPal). The
 * reservas become a sale and the gateway charges it, all or nothing.
 */
final class PurchasesTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private const string INTENTS = 'api.onvopay.com/v1/payment-intents';

    private NegocioModel $negocio;

    private UserModel $ana;

    private UserModel $beto;

    private ProductModel $leche;

    private ProductModel $arroz;

    /** A negocio with two products and two clientes; both gateways have test credentials. */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.default' => 'onvo',
            'services.onvo.secret' => 'onvo_test_secret_key_feature',
            'services.onvo.base_url' => 'https://api.onvopay.com/v1',
            'services.onvo.webhook_secret' => 'whsec_test',
            'services.paypal.client_id' => 'client',
            'services.paypal.client_secret' => 'secret',
            'services.paypal.base_url' => 'https://api-m.sandbox.paypal.com',
            'services.paypal.colones_per_dollar' => 500.0,
            'services.paypal.return_url' => 'https://app.test/pago/ok',
        ]);

        $this->negocio = NegocioModel::factory()->create();
        $this->leche = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Leche entera 1 L', 'precio_venta' => '950.00', 'stock' => 5]);
        $this->arroz = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Arroz 1 kg', 'precio_venta' => '1100.00', 'stock' => 10]);
        $this->ana = UserModel::factory()->role(Role::Cliente)->create(['negocio_id' => $this->negocio->id]);
        $this->beto = UserModel::factory()->role(Role::Cliente)->create(['negocio_id' => $this->negocio->id]);
    }

    /** What the cliente holds is bought with ONVO: one sale, the units leave the stock, the reservas are confirmed, the payment is paid. */
    public function test_the_cliente_buys_its_reservas_and_pays_with_onvo(): void
    {
        $this->actAs($this->ana);
        $this->reserve($this->leche, 2);
        $this->reserve($this->arroz, 1);
        $this->fakeOnvo('succeeded');

        $this->postJson('/api/v1/compras', ['metodo_pago' => 'tarjeta', 'pasarela' => 'onvo', 'metodo_proveedor_id' => 'pm_test_123'])
            ->assertCreated()
            ->assertJsonPath('data.proveedor', 'onvo')
            ->assertJsonPath('data.estado', 'pagado')
            ->assertJsonPath('data.monto', fn (mixed $amount): bool => (float) $amount === 3000.0);

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseCount('ventas', 1);
        $this->assertDatabaseHas('ventas', ['cajero_id' => $this->ana->id]);
        $this->assertDatabaseHas('productos', ['id' => $this->leche->id, 'stock' => 3]);
        $this->assertDatabaseHas('productos', ['id' => $this->arroz->id, 'stock' => 9]);
        $this->assertSame(2, DB::table('reservas')->where('usuario_id', $this->ana->id)->where('estado', 'confirmada')->count());
        $this->assertDatabaseHas('pagos', ['proveedor' => 'onvo', 'estado' => 'pagado']);
    }

    /** The cliente can choose PayPal for this payment although ONVO is the configured gateway; PayPal asks nothing of the client. */
    public function test_the_cliente_chooses_paypal_for_the_payment_and_follows_it(): void
    {
        $this->actAs($this->ana);
        $this->reserve($this->leche, 1);
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'access_token_1']),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response(['id' => 'ORDER1', 'status' => 'PAYER_ACTION_REQUIRED', 'links' => [['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER1']]], 201),
        ]);

        $saleId = $this->postJson('/api/v1/compras', ['metodo_pago' => 'tarjeta', 'pasarela' => 'paypal'])
            ->assertCreated()
            ->assertJsonPath('data.proveedor', 'paypal')
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.url_aprobacion', 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER1')
            ->json('data.venta_id');

        Http::fake([
            'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER1/capture' => Http::response(['id' => 'ORDER1', 'status' => 'COMPLETED', 'purchase_units' => [['payments' => ['captures' => [['status' => 'COMPLETED']]]]]], 201),
            'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER1' => Http::response(['id' => 'ORDER1', 'status' => 'APPROVED']),
        ]);

        $this->getJson('/api/v1/compras/'.$saleId.'/pago')
            ->assertOk()
            ->assertJsonPath('data.proveedor', 'paypal')
            ->assertJsonPath('data.estado', 'pagado');
    }

    /** Nobody sees the payment of a purchase of another cliente. */
    public function test_a_cliente_cannot_see_the_payment_of_another_cliente(): void
    {
        $this->actAs($this->ana);
        $this->reserve($this->leche, 1);
        $this->fakeOnvo('succeeded');
        $saleId = $this->postJson('/api/v1/compras', ['metodo_pago' => 'tarjeta', 'pasarela' => 'onvo', 'metodo_proveedor_id' => 'pm_test_123'])->assertCreated()->json('data.venta_id');

        $this->actAs($this->beto);

        $this->getJson('/api/v1/compras/'.$saleId.'/pago')->assertNotFound();
    }

    /** Without reservas there is nothing to buy: 409 and nothing is stored. */
    public function test_buying_without_reservas_is_a_conflict(): void
    {
        $this->actAs($this->ana);
        Http::fake();

        $this->postJson('/api/v1/compras', ['metodo_pago' => 'tarjeta', 'pasarela' => 'onvo', 'metodo_proveedor_id' => 'pm_test_123'])
            ->assertConflict()
            ->assertJsonPath('detail', 'No tiene reservas activas para comprar: reserve productos primero o vuelva a reservar si vencieron.');

        Http::assertNothingSent();
        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseCount('ventas', 0);
    }

    /** If the gateway refuses the card nothing is kept: no sale, the stock is whole and the reservas stay active to try again. */
    public function test_a_refused_payment_keeps_the_reservas_active(): void
    {
        $this->actAs($this->ana);
        $this->reserve($this->leche, 2);
        Http::fake([
            self::INTENTS => Http::response(['id' => 'intent_1', 'status' => 'requires_payment_method'], 201),
            self::INTENTS.'/intent_1/confirm' => Http::response([
                'code' => 'cards.invalid_card_info',
                'details' => ['card' => ['reason' => 'issuer_declined', 'declineCode' => 'insufficient_funds']],
            ], 400),
        ]);

        $this->postJson('/api/v1/compras', ['metodo_pago' => 'tarjeta', 'pasarela' => 'onvo', 'metodo_proveedor_id' => 'pm_test_123'])->assertConflict();

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseCount('ventas', 0);
        $this->assertDatabaseCount('pagos', 0);
        $this->assertDatabaseHas('productos', ['id' => $this->leche->id, 'stock' => 5]);
        $this->assertSame(1, DB::table('reservas')->where('usuario_id', $this->ana->id)->where('estado', 'activa')->count());
    }

    /** Buying takes only the reservas of the cliente that buys: the ones of another cliente keep holding their units. */
    public function test_the_reservas_of_another_cliente_stay_active(): void
    {
        $this->actAs($this->beto);
        $this->reserve($this->arroz, 3);
        $this->actAs($this->ana);
        $this->reserve($this->leche, 1);
        $this->fakeOnvo('succeeded');

        $this->postJson('/api/v1/compras', ['metodo_pago' => 'tarjeta', 'pasarela' => 'onvo', 'metodo_proveedor_id' => 'pm_test_123'])->assertCreated();

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame(1, DB::table('reservas')->where('usuario_id', $this->beto->id)->where('estado', 'activa')->count());
        $this->assertDatabaseHas('productos', ['id' => $this->arroz->id, 'stock' => 10]);
    }

    /** Liquor needs the id number of the buyer, as in the till: without it the purchase is refused and the reserva stays. */
    public function test_liquor_needs_the_id_number_of_the_buyer(): void
    {
        $vino = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Vino tinto', 'precio_venta' => '5000.00', 'stock' => 4, 'es_licor' => true]);
        $this->actAs($this->ana);
        $this->reserve($vino, 1);
        $this->fakeOnvo('succeeded');

        $this->postJson('/api/v1/compras', ['metodo_pago' => 'tarjeta', 'pasarela' => 'onvo', 'metodo_proveedor_id' => 'pm_test_123'])->assertUnprocessable();

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseCount('ventas', 0);
        $this->assertSame(1, DB::table('reservas')->where('usuario_id', $this->ana->id)->where('estado', 'activa')->count());

        $this->postJson('/api/v1/compras', ['metodo_pago' => 'tarjeta', 'pasarela' => 'onvo', 'metodo_proveedor_id' => 'pm_test_123', 'cedula_comprador' => '1-0234-0567'])->assertCreated();
    }

    /** The gateway is required here, ONVO asks for its payment method and an unknown gateway is refused. */
    public function test_the_data_of_the_purchase_is_validated(): void
    {
        $this->actAs($this->ana);
        Http::fake();

        $this->postJson('/api/v1/compras', [])->assertUnprocessable()->assertJsonValidationErrors(['metodo_pago', 'pasarela'], 'errores');
        $this->postJson('/api/v1/compras', ['metodo_pago' => 'tarjeta', 'pasarela' => 'onvo'])->assertUnprocessable()->assertJsonValidationErrors('metodo_proveedor_id', 'errores');
        $this->postJson('/api/v1/compras', ['metodo_pago' => 'tarjeta', 'pasarela' => 'stripe'])->assertUnprocessable()->assertJsonValidationErrors('pasarela', 'errores');

        Http::assertNothingSent();
    }

    /** Hold units of a product for the cliente that is acting. */
    private function reserve(ProductModel $product, int $quantity): void
    {
        $this->postJson('/api/v1/reservas', ['producto_id' => $product->id, 'cantidad' => $quantity])->assertCreated();
    }

    /** Act as the cliente with the abilities of its role. */
    private function actAs(UserModel $user): void
    {
        Sanctum::actingAs($user, Role::Cliente->abilities());
    }

    /** Answer the creation of the ONVO intent and its confirmation with the given status. */
    private function fakeOnvo(string $status): void
    {
        Http::fake([
            self::INTENTS => Http::response(['id' => 'intent_1', 'status' => 'requires_payment_method'], 201),
            self::INTENTS.'/intent_1/confirm' => Http::response(['id' => 'intent_1', 'status' => $status, 'amount' => 300000, 'currency' => 'CRC'], 201),
        ]);
    }
}
