<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Laravel\Sanctum\Sanctum;
use Src\Credit\Customers\Infrastructure\Persistence\Models\CustomerModel;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToNegocio;
use Tests\TestCase;

/**
 * US-308: the cashier writes the sale in free text and gets a structured draft, validated with a schema
 * and checked against the real catalog, stock and limit of fiado. The provider is always simulated.
 */
final class SaleDraftFromTextTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private const string ENDPOINT = '/api/v1/ventas/borrador-ia';

    private const string PROVIDER = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    private NegocioModel $negocio;

    private ProductModel $leche;

    private ProductModel $pan;

    private CustomerModel $maria;

    /** A negocio with milk, bread and Doña María; the provider is configured with a test key. */
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'gemini-test-key', 'services.gemini.model' => 'gemini-2.5-flash', 'services.gemini.base_url' => 'https://generativelanguage.googleapis.com']);
        Sleep::fake();

        $this->negocio = NegocioModel::factory()->create();
        $this->leche = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Leche entera 1 L', 'precio_venta' => '950.00', 'stock' => 30]);
        $this->pan = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Pan cuadrado', 'precio_venta' => '1450.00', 'stock' => 1]);
        $this->maria = CustomerModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Doña María Rodríguez', 'limite_fiado' => '50000.00']);
        Sanctum::actingAs(UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id]), Role::Cajero->abilities());
    }

    /** "anote 2 leches y 1 pan a doña María en fiado" becomes a draft with the products, the customer and the fiado checked. */
    public function test_text_becomes_a_structured_draft(): void
    {
        $this->providerAnswers(['cliente' => 'doña María', 'metodo_pago' => 'fiado', 'lineas' => [
            ['producto' => 'leches', 'cantidad' => 2],
            ['producto' => 'pan', 'cantidad' => 1],
        ]]);

        $this->postJson(self::ENDPOINT, ['texto' => 'anote 2 leches y 1 pan a doña María en fiado'])
            ->assertOk()
            ->assertJsonPath('data.metodo_pago', 'fiado')
            ->assertJsonPath('data.cliente.id', $this->maria->id)
            ->assertJsonPath('data.cliente.disponible', 50000)
            ->assertJsonPath('data.lineas.0.producto_id', $this->leche->id)
            ->assertJsonPath('data.lineas.0.mencionado', 'leches')
            ->assertJsonPath('data.lineas.0.cantidad', 2)
            ->assertJsonPath('data.lineas.0.subtotal', 1900)
            ->assertJsonPath('data.lineas.1.producto_id', $this->pan->id)
            ->assertJsonPath('data.total', 3350)
            ->assertJsonPath('data.cabe_en_fiado', true)
            ->assertJsonPath('data.avisos', [])
            ->assertJsonPath('data.listo_para_confirmar', true)
            ->assertJsonPath('data.confirmar', [
                'venta' => ['lineas' => [['producto_id' => $this->leche->id, 'cantidad' => 2], ['producto_id' => $this->pan->id, 'cantidad' => 1]]],
                'fiado' => ['cliente_id' => $this->maria->id],
            ]);

        Http::assertSent(fn (Request $request): bool => $request->url() === self::PROVIDER
            && $request->hasHeader('x-goog-api-key', 'gemini-test-key')
            && $request['generationConfig']['responseMimeType'] === 'application/json'
            && $request['contents'][0]['parts'][0]['text'] === 'anote 2 leches y 1 pan a doña María en fiado'
            && str_contains($request['systemInstruction']['parts'][0]['text'], 'Leche entera 1 L'));
    }

    /** The draft is never registered: no sale, no stock movement and no fiado until the cashier confirms. */
    public function test_draft_never_registers_the_sale(): void
    {
        $this->providerAnswers(['cliente' => null, 'metodo_pago' => 'efectivo', 'lineas' => [['producto' => 'Leche entera 1 L', 'cantidad' => 3]]]);

        $this->postJson(self::ENDPOINT, ['texto' => '3 leches de contado'])->assertOk()->assertJsonPath('data.listo_para_confirmar', true);

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame(0, DB::table('ventas')->count());
        $this->assertSame(30, (int) DB::table('productos')->where('id', $this->leche->id)->value('stock'));
        $this->assertSame(0, DB::table('movimientos_fiado')->count());
    }

    /** The cashier confirms the draft with the usual endpoints and the sale is registered. */
    public function test_cashier_confirms_the_draft_by_hand(): void
    {
        $this->providerAnswers(['cliente' => 'María', 'metodo_pago' => 'fiado', 'lineas' => [['producto' => 'leche', 'cantidad' => 2]]]);
        $confirm = $this->postJson(self::ENDPOINT, ['texto' => 'dos leches a María al fiado'])->json('data.confirmar');

        $sale = $this->postJson('/api/v1/ventas', $confirm['venta'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/ventas/{$sale}/fiado", $confirm['fiado'])->assertOk()->assertJsonPath('data.cliente.saldo_actual', 1900);
    }

    /** The draft is contrasted with the real stock and the limit of fiado, and warns instead of failing. */
    public function test_draft_warns_about_stock_and_fiado_limit(): void
    {
        $this->maria->update(['limite_fiado' => '2000.00']);
        $this->providerAnswers(['cliente' => 'Doña María', 'metodo_pago' => 'fiado', 'lineas' => [['producto' => 'pan', 'cantidad' => 2]]]);

        $this->postJson(self::ENDPOINT, ['texto' => '2 panes a doña María'])
            ->assertOk()
            ->assertJsonPath('data.lineas.0.alcanza', false)
            ->assertJsonPath('data.lineas.0.disponible_para_vender', 1)
            ->assertJsonPath('data.cabe_en_fiado', false)
            ->assertJsonPath('data.listo_para_confirmar', false)
            ->assertJsonPath('data.avisos', [
                'No alcanza el stock de Pan cuadrado: se piden 2 y hay 1 para vender.',
                'La venta no cabe en el fiado de Doña María Rodríguez: le quedan ₡2 000 disponibles.',
            ]);
    }

    /** What matches nothing of the negocio is reported, never invented; products of another negocio are not candidates. */
    public function test_unknown_products_and_customers_are_reported(): void
    {
        $other = NegocioModel::factory()->create();
        ProductModel::factory()->create(['negocio_id' => $other->id, 'nombre' => 'Café molido 250 g']);
        $this->providerAnswers(['cliente' => 'Josefina', 'metodo_pago' => null, 'lineas' => [['producto' => 'café', 'cantidad' => 1], ['producto' => 'leche', 'cantidad' => 1]]]);

        $this->postJson(self::ENDPOINT, ['texto' => 'un café y una leche para Josefina'])
            ->assertOk()
            ->assertJsonPath('data.metodo_pago', 'fiado')
            ->assertJsonPath('data.cliente', null)
            ->assertJsonPath('data.cliente_mencionado', 'Josefina')
            ->assertJsonPath('data.no_encontrados', [['mencionado' => 'café', 'cantidad' => 1]])
            ->assertJsonPath('data.lineas.0.producto_id', $this->leche->id)
            ->assertJsonPath('data.avisos', [
                'No se encontró "café" en el catálogo; agréguelo a mano.',
                'No se encontró al cliente "Josefina"; selecciónelo a mano.',
            ])
            ->assertJsonPath('data.confirmar.fiado', null);
    }

    /** If the provider fails, the API answers a controlled 503 and the manual flow keeps working. */
    public function test_provider_failure_is_a_controlled_error(): void
    {
        Http::fake([self::PROVIDER => Http::response(['error' => ['code' => 503, 'status' => 'UNAVAILABLE']], 503)]);

        $this->postJson(self::ENDPOINT, ['texto' => '2 leches'])
            ->assertStatus(503)
            ->assertHeader('Retry-After')
            ->assertJsonPath('type', 'http://localhost/problemas/servicio-no-disponible')
            ->assertJsonPath('detail', 'El asistente de IA no está disponible en este momento. Registre la venta a mano.');

        Http::assertSentCount(2);
        $this->postJson('/api/v1/ventas', ['lineas' => [['producto_id' => $this->leche->id, 'cantidad' => 2]]])->assertCreated();
    }

    /** A timeout or a dropped connection is the same controlled error. */
    public function test_timeout_is_a_controlled_error(): void
    {
        Http::fake([self::PROVIDER => fn () => throw new ConnectionException('cURL error 28: Operation timed out')]);

        $this->postJson(self::ENDPOINT, ['texto' => '2 leches'])->assertStatus(503);
    }

    /** An answer that does not pass the schema is never used. */
    public function test_invalid_answer_is_refused(): void
    {
        foreach ([
            ['cliente' => null, 'metodo_pago' => 'tarjeta', 'lineas' => [['producto' => 'leche', 'cantidad' => 1]]],
            ['cliente' => null, 'metodo_pago' => null, 'lineas' => []],
            ['cliente' => null, 'metodo_pago' => null, 'lineas' => [['producto' => 'leche', 'cantidad' => 0]]],
            ['cliente' => null, 'metodo_pago' => null, 'lineas' => [['producto' => 'leche', 'cantidad' => 'dos']]],
        ] as $answer) {
            $this->providerAnswers($answer);

            $this->postJson(self::ENDPOINT, ['texto' => 'algo'])
                ->assertStatus(503)
                ->assertJsonPath('detail', 'El asistente de IA respondió algo que no se pudo usar. Intente de nuevo o registre la venta a mano.');
        }

        Http::fake([self::PROVIDER => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Claro, aquí tiene su venta.']]]]]])]);
        $this->postJson(self::ENDPOINT, ['texto' => 'algo'])->assertStatus(503);
    }

    /** Without a key the provider is not called and the answer is the controlled error. */
    public function test_without_a_key_nothing_is_sent(): void
    {
        config(['services.gemini.key' => null]);
        Http::fake();

        $this->postJson(self::ENDPOINT, ['texto' => '2 leches'])->assertStatus(503);

        Http::assertNothingSent();
    }

    /** The text is required and short; the warehouse keeper cannot draft sales. */
    public function test_validation_and_permissions(): void
    {
        Http::fake();

        $this->postJson(self::ENDPOINT, [])->assertUnprocessable()->assertJsonPath('errores.texto.0', 'El campo texto es obligatorio.');
        $this->postJson(self::ENDPOINT, ['texto' => str_repeat('a', 501)])->assertUnprocessable();

        Sanctum::actingAs(UserModel::factory()->role(Role::Bodeguero)->create(['negocio_id' => $this->negocio->id]), Role::Bodeguero->abilities());
        $this->postJson(self::ENDPOINT, ['texto' => '2 leches'])->assertForbidden();

        Http::assertNothingSent();
    }

    /**
     * The provider answers this JSON, the way generateContent returns it.
     *
     * @param  array<string, mixed>  $json
     */
    private function providerAnswers(array $json): void
    {
        Http::fake([self::PROVIDER => Http::response([
            'candidates' => [[
                'content' => ['role' => 'model', 'parts' => [['text' => json_encode($json, JSON_UNESCAPED_UNICODE)]]],
                'finishReason' => 'STOP',
            ]],
        ])]);
    }
}
