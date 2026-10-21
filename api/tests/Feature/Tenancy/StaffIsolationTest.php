<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\Factories\SaleFactory;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToNegocio;
use Tests\TestCase;

/**
 * An account of one negocio cannot reach the products, deliveries, sales or expired units of another one
 * through any endpoint of the API, and the data of the other negocio stays exactly as it was.
 *
 * The account is an administrator, who has every ability, so a 403 can never hide a missing check of the negocio.
 */
final class StaffIsolationTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private const string NOT_FOUND = 'http://localhost/problemas/no-encontrado';

    private NegocioModel $negocio;

    private NegocioModel $otherNegocio;

    private UserModel $otherCashier;

    private ProductModel $ownProduct;

    private ProductModel $foreignProduct;

    private string $foreignReceipt;

    private string $foreignSale;

    /** Two negocios, each with a product; the second one also has a delivery and an open sale. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->negocio = NegocioModel::factory()->create(['nombre' => 'Minisúper A']);
        $this->otherNegocio = NegocioModel::factory()->create(['nombre' => 'Minisúper B']);
        $this->otherCashier = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->otherNegocio->id]);
        $this->ownProduct = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Arroz de A', 'stock' => 10]);
        $this->foreignProduct = ProductModel::factory()->create(['negocio_id' => $this->otherNegocio->id, 'nombre' => 'Arroz de B', 'precio_venta' => '1500.00', 'stock' => 10]);

        $this->foreignReceipt = $this->receiptOf($this->otherNegocio, $this->otherCashier, $this->foreignProduct);
        $this->foreignSale = SaleFactory::new()->for($this->otherNegocio, $this->otherCashier)->line($this->foreignProduct, 2)->create();

        Sanctum::actingAs(UserModel::factory()->create(['negocio_id' => $this->negocio->id]), Role::Administrador->abilities());
    }

    /**
     * Every request that names a resource of another negocio answers 404, and nothing of it changes.
     *
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('requestsOnForeignResources')]
    public function test_foreign_resources_answer_not_found_and_stay_untouched(string $method, string $uri, array $payload): void
    {
        $uri = strtr($uri, [
            '{producto}' => $this->foreignProduct->id,
            '{entrada}' => $this->foreignReceipt,
            '{venta}' => $this->foreignSale,
        ]);
        array_walk_recursive($payload, function (mixed &$value): void {
            $value = $value === '{own}' ? $this->ownProduct->id : $value;
        });

        $this->json($method, '/api/v1'.$uri, $payload)
            ->assertNotFound()
            ->assertJsonPath('type', self::NOT_FOUND);

        $this->scopeQueriesTo($this->otherNegocio->id);
        $product = DB::table('productos')->where('id', $this->foreignProduct->id)->first();
        $this->assertNotNull($product);
        $this->assertNull($product->deleted_at);
        $this->assertSame('Arroz de B', $product->nombre);
        $this->assertSame('1500.00', $product->precio_venta);
        $this->assertSame(10, (int) $product->stock);
        $this->assertSame(0, DB::table('unidades_vencidas')->count());
        $this->assertSame(1, DB::table('entradas_mercaderia')->where('id', $this->foreignReceipt)->count());

        $sale = DB::table('ventas')->where('id', $this->foreignSale)->first();
        $this->assertSame('abierta', $sale->estado);
        $this->assertSame([2], DB::table('venta_lineas')->where('venta_id', $this->foreignSale)->pluck('cantidad')->map(fn ($quantity): int => (int) $quantity)->all());
    }

    /**
     * Requests that reach a resource by its identifier: the verb, the route and the body it sends.
     *
     * @return array<string, array{string, string, array<string, mixed>}>
     */
    public static function requestsOnForeignResources(): array
    {
        return [
            'ver producto' => ['GET', '/productos/{producto}', []],
            'reemplazar producto' => ['PUT', '/productos/{producto}', ['codigo' => 'NUEVO-1', 'nombre' => 'Nuevo', 'precio_venta' => 1000]],
            'borrar producto' => ['DELETE', '/productos/{producto}', []],
            'cambiar precio' => ['PATCH', '/productos/{producto}/precio', ['precio_venta' => 2000]],
            'historial de precios' => ['GET', '/productos/{producto}/historial-precios', []],
            'disponibilidad' => ['GET', '/productos/{producto}/disponibilidad', []],
            'marcar vencidas' => ['POST', '/productos/{producto}/vencidos', ['cantidad' => 1, 'motivo' => 'Vencida']],
            'ver entrada' => ['GET', '/entradas/{entrada}', []],
            'ver venta' => ['GET', '/ventas/{venta}', []],
            'revisar venta' => ['PUT', '/ventas/{venta}', ['lineas' => [['producto_id' => '{own}', 'cantidad' => 1]]]],
            'cobrar venta' => ['POST', '/ventas/{venta}/cobrar', []],
            'anular venta' => ['POST', '/ventas/{venta}/anular', []],
        ];
    }

    /** A sale cannot take the products of another negocio either: they are reported as unavailable. */
    public function test_a_sale_cannot_use_the_products_of_another_negocio(): void
    {
        $this->postJson('/api/v1/ventas', ['lineas' => [['producto_id' => $this->foreignProduct->id, 'cantidad' => 1]]])
            ->assertStatus(422);

        $this->scopeQueriesTo($this->otherNegocio->id);
        $this->assertSame(10, (int) DB::table('productos')->where('id', $this->foreignProduct->id)->value('stock'));
    }

    /** The catalog and the profile only show what belongs to the negocio of the account. */
    public function test_lists_and_profile_show_only_the_own_negocio(): void
    {
        $this->getJson('/api/v1/productos')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.nombre', 'Arroz de A');

        $this->getJson('/api/v1/negocio')->assertOk()->assertJsonPath('data.nombre', 'Minisúper A');
    }

    /** The rule is symmetric: the other negocio cannot reach the sale or the product of the first one. */
    public function test_the_other_negocio_cannot_reach_the_first_one(): void
    {
        $cashier = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id]);
        $sale = SaleFactory::new()->for($this->negocio, $cashier)->line($this->ownProduct, 1)->create();

        Sanctum::actingAs($this->otherCashier, Role::Cajero->abilities());

        $this->getJson('/api/v1/ventas/'.$sale)->assertNotFound();
        $this->postJson('/api/v1/ventas/'.$sale.'/cobrar')->assertNotFound();
        $this->postJson('/api/v1/ventas/'.$sale.'/anular')->assertNotFound();
        $this->getJson('/api/v1/productos/'.$this->ownProduct->id.'/disponibilidad')->assertNotFound();

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame('abierta', DB::table('ventas')->where('id', $sale)->value('estado'));
    }

    /** A delivery of the negocio, with one line of the product, written as the module would leave it. */
    private function receiptOf(NegocioModel $negocio, UserModel $user, ProductModel $product): string
    {
        $this->scopeQueriesTo($negocio->id);

        $id = (string) Str::uuid7();
        $now = now();

        DB::table('entradas_mercaderia')->insert([
            'id' => $id,
            'negocio_id' => $negocio->id,
            'proveedor' => 'Distribuidora ficticia',
            'fecha' => $now->format('Y-m-d'),
            'registrada_por' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('entrada_lineas')->insert([
            'id' => (string) Str::uuid7(),
            'negocio_id' => $negocio->id,
            'entrada_id' => $id,
            'producto_id' => $product->id,
            'cantidad' => 5,
            'costo_unitario' => '1000.00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }
}
