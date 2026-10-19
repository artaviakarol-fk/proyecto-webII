<?php

declare(strict_types=1);

namespace Tests\Feature\Ventas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToNegocio;
use Tests\TestCase;

final class VentaTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private const string ENDPOINT = '/api/v1/ventas';

    private NegocioModel $negocio;

    private NegocioModel $otherNegocio;

    private UserModel $cajero;

    private ProductModel $arroz;

    private ProductModel $frijoles;

    /** Two negocios, two products of the first one (10 and 5 units) and its cashier, on a fixed day. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-15 09:30:00');
        $this->negocio = NegocioModel::factory()->create();
        $this->otherNegocio = NegocioModel::factory()->create();
        $this->arroz = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'codigo' => 'ARROZ-1', 'nombre' => 'Arroz 1 kg', 'precio_venta' => '1500.00', 'stock' => 10]);
        $this->frijoles = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'codigo' => 'FRIJOL-1', 'nombre' => 'Frijoles 1 kg', 'precio_venta' => '2000.00', 'stock' => 5]);
        $this->cajero = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Ana Caja']);
        Sanctum::actingAs($this->cajero, Role::Cajero->abilities());
    }

    /** A sale has lines with the catalog price, a total, the payment method and the cashier. */
    public function test_sale_has_lines_total_payment_method_and_cashier(): void
    {
        $response = $this->postJson(self::ENDPOINT, ['lineas' => [
            ['producto_id' => $this->arroz->id, 'cantidad' => 2],
            ['producto_id' => $this->frijoles->id, 'cantidad' => 1],
        ]]);

        $id = $response->json('data.id');
        $response
            ->assertCreated()
            ->assertHeader('Location', 'http://localhost'.self::ENDPOINT.'/'.$id)
            ->assertExactJson(['data' => [
                'id' => $id,
                'estado' => 'abierta',
                'metodo_pago' => 'efectivo',
                'cajero' => ['id' => $this->cajero->id, 'nombre' => 'Ana Caja'],
                'cedula_comprador' => null,
                'creada_en' => '2026-10-15T09:30:00+00:00',
                'cobrada_en' => null,
                'anulada_en' => null,
                'lineas' => [
                    ['producto_id' => $this->arroz->id, 'codigo' => 'ARROZ-1', 'nombre' => 'Arroz 1 kg', 'cantidad' => 2, 'precio_unitario' => 1500, 'subtotal' => 3000],
                    ['producto_id' => $this->frijoles->id, 'codigo' => 'FRIJOL-1', 'nombre' => 'Frijoles 1 kg', 'cantidad' => 1, 'precio_unitario' => 2000, 'subtotal' => 2000],
                ],
                'total' => 5000,
                'advertencias' => [],
            ]]);
    }

    /** The price comes from the catalog: a price sent by the client is ignored. */
    public function test_price_comes_from_the_catalog(): void
    {
        $this->postJson(self::ENDPOINT, ['lineas' => [
            ['producto_id' => $this->arroz->id, 'cantidad' => 2, 'precio_unitario' => 1],
        ]])
            ->assertCreated()
            ->assertJsonPath('data.lineas.0.precio_unitario', 1500)
            ->assertJsonPath('data.total', 3000);
    }

    /** The stock goes down by the units sold and the movement records who, what and the resulting stock. */
    public function test_sale_takes_units_out_of_the_stock_and_leaves_a_movement(): void
    {
        $id = $this->sell([[$this->arroz, 3], [$this->frijoles, 5]])->json('data.id');

        $this->assertSame(7, $this->stockOf($this->arroz));
        $this->assertSame(0, $this->stockOf($this->frijoles));

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseHas('movimientos_inventario', [
            'producto_id' => $this->arroz->id,
            'tipo' => 'venta',
            'cantidad' => 3,
            'stock_resultante' => 7,
            'referencia_id' => $id,
            'usuario_id' => $this->cajero->id,
        ]);
    }

    /** A sale is consulted by its id. */
    public function test_sale_is_consulted(): void
    {
        $id = $this->sell([[$this->arroz, 1]])->json('data.id');

        $this->getJson(self::ENDPOINT.'/'.$id)
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.total', 1500);
    }

    /** An open sale changes its lines and the stock follows the difference. */
    public function test_open_sale_is_revised_and_the_stock_follows(): void
    {
        $id = $this->sell([[$this->arroz, 4], [$this->frijoles, 2]])->json('data.id');

        $this->putJson(self::ENDPOINT.'/'.$id, ['lineas' => [
            ['producto_id' => $this->arroz->id, 'cantidad' => 6],
        ]])
            ->assertOk()
            ->assertJsonCount(1, 'data.lineas')
            ->assertJsonPath('data.total', 9000);

        $this->assertSame(4, $this->stockOf($this->arroz));
        $this->assertSame(5, $this->stockOf($this->frijoles));
    }

    /** A paid sale is closed: it cannot be revised or paid again. */
    public function test_paid_sale_cannot_change(): void
    {
        $id = $this->sell([[$this->arroz, 1]])->json('data.id');

        $this->postJson(self::ENDPOINT.'/'.$id.'/cobrar')
            ->assertOk()
            ->assertJsonPath('data.estado', 'cobrada')
            ->assertJsonPath('data.cobrada_en', '2026-10-15T09:30:00+00:00');

        $this->putJson(self::ENDPOINT.'/'.$id, ['lineas' => [['producto_id' => $this->arroz->id, 'cantidad' => 2]]])
            ->assertConflict();
        $this->postJson(self::ENDPOINT.'/'.$id.'/cobrar')->assertConflict();
        $this->assertSame(9, $this->stockOf($this->arroz));
    }

    /** Cancelling a sale gives its units back to the stock and leaves a movement. */
    public function test_cancelling_returns_the_stock(): void
    {
        $id = $this->sell([[$this->arroz, 4], [$this->frijoles, 5]])->json('data.id');

        $this->postJson(self::ENDPOINT.'/'.$id.'/anular')
            ->assertOk()
            ->assertJsonPath('data.estado', 'anulada')
            ->assertJsonPath('data.anulada_en', '2026-10-15T09:30:00+00:00');

        $this->assertSame(10, $this->stockOf($this->arroz));
        $this->assertSame(5, $this->stockOf($this->frijoles));

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseHas('movimientos_inventario', [
            'producto_id' => $this->arroz->id,
            'tipo' => 'anulacion',
            'cantidad' => 4,
            'stock_resultante' => 10,
            'referencia_id' => $id,
        ]);
    }

    /** A paid sale can still be cancelled, but a cancelled one cannot be cancelled, revised or paid. */
    public function test_cancelled_sale_is_final_and_a_paid_one_can_be_cancelled(): void
    {
        $id = $this->sell([[$this->arroz, 4]])->json('data.id');
        $this->postJson(self::ENDPOINT.'/'.$id.'/cobrar')->assertOk();

        $this->postJson(self::ENDPOINT.'/'.$id.'/anular')->assertOk();
        $this->postJson(self::ENDPOINT.'/'.$id.'/anular')->assertConflict();
        $this->postJson(self::ENDPOINT.'/'.$id.'/cobrar')->assertConflict();
        $this->putJson(self::ENDPOINT.'/'.$id, ['lineas' => [['producto_id' => $this->arroz->id, 'cantidad' => 1]]])->assertConflict();
        $this->assertSame(10, $this->stockOf($this->arroz));
    }

    /** Expired units still on the shelf are not sold. */
    public function test_expired_units_cannot_be_sold(): void
    {
        $this->scopeQueriesTo($this->negocio->id);
        DB::table('unidades_vencidas')->insert([
            'id' => (string) Str::uuid7(),
            'negocio_id' => $this->negocio->id,
            'producto_id' => $this->arroz->id,
            'cantidad' => 8,
            'motivo' => 'Vencidas en el estante',
            'estado' => 'activa',
            'usuario_id' => $this->cajero->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->sell([[$this->arroz, 3]])
            ->assertConflict()
            ->assertJsonPath('detail', 'No alcanza el stock de Arroz 1 kg. Disponible para vender: 2.');

        $this->sell([[$this->arroz, 2]])->assertCreated();
    }

    /**
     * Bad requests answer 422 and sell nothing.
     *
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_sale_is_rejected(array $payload, string $field): void
    {
        $payload = json_decode(str_replace('{arroz}', $this->arroz->id, (string) json_encode($payload)), true);

        $this->postJson(self::ENDPOINT, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field, 'errores');

        $this->assertSame(10, $this->stockOf($this->arroz));
    }

    /**
     * Payloads that must be rejected, with the field that carries the error.
     *
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'without lines' => [[], 'lineas'];
        yield 'empty lines' => [['lineas' => []], 'lineas'];
        yield 'zero quantity' => [['lineas' => [['producto_id' => '{arroz}', 'cantidad' => 0]]], 'lineas.0.cantidad'];
        yield 'fractional quantity' => [['lineas' => [['producto_id' => '{arroz}', 'cantidad' => 1.5]]], 'lineas.0.cantidad'];
        yield 'unknown product' => [['lineas' => [['producto_id' => '019a0000-7b1e-7c3a-9f1d-2b6a4e8c0d99', 'cantidad' => 1]]], 'lineas.0.producto_id'];
        yield 'repeated product' => [['lineas' => [['producto_id' => '{arroz}', 'cantidad' => 1], ['producto_id' => '{arroz}', 'cantidad' => 2]]], 'lineas.0.producto_id'];
    }

    /** A product of another negocio cannot be sold. */
    public function test_product_of_another_negocio_cannot_be_sold(): void
    {
        $foreign = ProductModel::factory()->create(['negocio_id' => $this->otherNegocio->id, 'stock' => 10]);

        $this->sell([[$foreign, 1]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lineas.0.producto_id', 'errores');
    }

    /** A sale of another negocio answers 404, as if it did not exist, for every operation. */
    public function test_sale_of_another_negocio_answers_not_found(): void
    {
        $foreign = ProductModel::factory()->create(['negocio_id' => $this->otherNegocio->id, 'stock' => 10]);
        $foreignCashier = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->otherNegocio->id]);
        Sanctum::actingAs($foreignCashier, Role::Cajero->abilities());
        $id = $this->sell([[$foreign, 2]])->json('data.id');
        Sanctum::actingAs($this->cajero, Role::Cajero->abilities());

        $lines = ['lineas' => [['producto_id' => $this->arroz->id, 'cantidad' => 1]]];
        $this->getJson(self::ENDPOINT.'/'.$id)->assertNotFound();
        $this->putJson(self::ENDPOINT.'/'.$id, $lines)->assertNotFound();
        $this->postJson(self::ENDPOINT.'/'.$id.'/cobrar')->assertNotFound();
        $this->postJson(self::ENDPOINT.'/'.$id.'/anular')->assertNotFound();
        $this->assertSame(8, $this->stockOf($foreign));
    }

    /** The cashier and the administration sell; the warehouse keeper and anonymous visitors do not. */
    public function test_only_the_register_staff_sells(): void
    {
        $lines = ['lineas' => [['producto_id' => $this->arroz->id, 'cantidad' => 1]]];

        $admin = UserModel::factory()->role(Role::Administrador)->create(['negocio_id' => $this->negocio->id]);
        Sanctum::actingAs($admin, Role::Administrador->abilities());
        $this->postJson(self::ENDPOINT, $lines)->assertCreated();

        $bodeguero = UserModel::factory()->role(Role::Bodeguero)->create(['negocio_id' => $this->negocio->id]);
        Sanctum::actingAs($bodeguero, Role::Bodeguero->abilities());
        $this->postJson(self::ENDPOINT, $lines)->assertForbidden();
    }

    /** Without a token the sales answer 401. */
    public function test_anonymous_visitors_are_rejected(): void
    {
        $this->app['auth']->forgetGuards();

        $this->postJson(self::ENDPOINT, ['lineas' => []])->assertUnauthorized();
    }

    /**
     * Sell products, each with its units.
     *
     * @param  list<array{ProductModel, int}>  $items
     */
    private function sell(array $items): TestResponse
    {
        return $this->postJson(self::ENDPOINT, ['lineas' => array_map(
            fn (array $item): array => ['producto_id' => $item[0]->id, 'cantidad' => $item[1]],
            $items,
        )]);
    }

    /** The units a product has now, read in the scope of its negocio. */
    private function stockOf(ProductModel $product): int
    {
        $this->scopeQueriesTo($product->negocio_id);

        return (int) DB::table('productos')->where('id', $product->id)->value('stock');
    }
}
