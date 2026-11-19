<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
 * A listing answers with the same number of queries whether the page has 2 rows or 12: nothing is asked again for
 * each row (the "N+1 queries" problem), so the time of a page does not grow with the data of the negocio.
 */
final class QueryCountTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private NegocioModel $negocio;

    private UserModel $staff;

    /** A negocio with one account of each kind needed to fill the data. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->negocio = NegocioModel::factory()->create();
        $this->staff = UserModel::factory()->role(Role::Administrador)->create(['negocio_id' => $this->negocio->id]);
    }

    /** The customers of a page bring their increases of the limit in one query, not one per customer. */
    public function test_customers_listing_does_not_query_once_per_customer(): void
    {
        $this->customersWithIncreases(2);
        Sanctum::actingAs($this->staff, Role::Administrador->abilities());
        $few = $this->queriesOf('/api/v1/clientes');

        $this->customersWithIncreases(10);
        $many = $this->queriesOf('/api/v1/clientes');

        $this->assertSame($few, $many);
    }

    /** The catalog of a page counts the expired and the reserved units of all its products at once. */
    public function test_catalog_does_not_query_once_per_product(): void
    {
        $cliente = UserModel::factory()->role(Role::Cliente)->create(['negocio_id' => $this->negocio->id]);
        $this->productsWithExpiredAndReservedUnits(2);
        Sanctum::actingAs($cliente, Role::Cliente->abilities());
        $few = $this->queriesOf('/api/v1/catalogo');

        $this->productsWithExpiredAndReservedUnits(10);
        $many = $this->queriesOf('/api/v1/catalogo');

        $this->assertSame($few, $many);
    }

    /** The catalog still answers the right units when it counts them for the whole page. */
    public function test_catalog_counts_each_product_with_its_own_units(): void
    {
        $cliente = UserModel::factory()->role(Role::Cliente)->create(['negocio_id' => $this->negocio->id]);
        $this->productsWithExpiredAndReservedUnits(3);
        Sanctum::actingAs($cliente, Role::Cliente->abilities());

        $products = $this->getJson('/api/v1/catalogo')->assertOk()->json('data');

        $this->assertCount(3, $products);
        foreach ($products as $product) {
            // 20 in stock, 2 expired and 3 reserved by another cliente: 15 can be bought.
            $this->assertSame(15, $product['disponible']);
        }
    }

    /** Number of SQL statements that one request runs. */
    private function queriesOf(string $uri): int
    {
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });
        $this->getJson($uri)->assertOk();

        return $count;
    }

    /** Customers of the negocio, each one with an active increase of its limit. */
    private function customersWithIncreases(int $amount): void
    {
        $this->scopeQueriesTo($this->negocio->id);
        foreach (CustomerModel::factory()->count($amount)->create(['negocio_id' => $this->negocio->id]) as $customer) {
            DB::table('ampliaciones_limite')->insert([
                'id' => (string) Str::uuid7(),
                'negocio_id' => $this->negocio->id,
                'cliente_id' => $customer->id,
                'monto' => '5000.00',
                'motivo' => 'Compra grande de fin de mes',
                'usuario_id' => $this->staff->id,
                'vigente_hasta' => now()->addDays(3),
            ]);
        }
    }

    /** Products with 20 units, 2 of them expired on the shelf and 3 held by a reserva of another cliente. */
    private function productsWithExpiredAndReservedUnits(int $amount): void
    {
        $other = UserModel::factory()->role(Role::Cliente)->create(['negocio_id' => $this->negocio->id]);
        $this->scopeQueriesTo($this->negocio->id);
        foreach (ProductModel::factory()->count($amount)->create(['negocio_id' => $this->negocio->id, 'stock' => 20]) as $product) {
            DB::table('unidades_vencidas')->insert([
                'id' => (string) Str::uuid7(),
                'negocio_id' => $this->negocio->id,
                'producto_id' => $product->id,
                'cantidad' => 2,
                'motivo' => 'Encontrada vencida en el estante',
                'estado' => 'activa',
                'usuario_id' => $this->staff->id,
            ]);
            DB::table('reservas')->insert([
                'id' => (string) Str::uuid7(),
                'negocio_id' => $this->negocio->id,
                'usuario_id' => $other->id,
                'producto_id' => $product->id,
                'cantidad' => 3,
                'estado' => 'activa',
                'expira_en' => now()->addMinutes(10),
            ]);
        }
    }
}
