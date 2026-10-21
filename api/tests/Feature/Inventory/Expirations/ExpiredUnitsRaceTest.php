<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory\Expirations;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToNegocio;
use Tests\TestCase;

/**
 * The warehouse marks expired units while the registers sell the same product. Both operations lock the
 * product row with SELECT ... FOR UPDATE, so whatever the order they arrive in, the units that were sold
 * plus the units that were marked never exceed the stock.
 */
final class ExpiredUnitsRaceTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private NegocioModel $negocio;

    private ProductModel $milk;

    private UserModel $warehouse;

    private UserModel $cashier;

    /** A product with 10 units, a warehouse worker and a cashier of the same negocio. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->negocio = NegocioModel::factory()->create();
        $this->milk = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Leche entera 1 L', 'stock' => 10]);
        $this->warehouse = UserModel::factory()->role(Role::Bodeguero)->create(['negocio_id' => $this->negocio->id]);
        $this->cashier = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id]);
    }

    /** Units sold first leave fewer units to mark: the mark is refused with a conflict and stores nothing. */
    public function test_units_already_sold_cannot_be_marked_as_expired(): void
    {
        $this->sell(8)->assertCreated();

        Sanctum::actingAs($this->warehouse, Role::Bodeguero->abilities());
        $this->postJson($this->markUrl(), ['cantidad' => 3, 'motivo' => 'Lote vencido'])
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json');

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame(0, DB::table('unidades_vencidas')->count());
        $this->assertSame(2, (int) DB::table('productos')->where('id', $this->milk->id)->value('stock'));
    }

    /** Units marked first leave fewer units to sell: the sale is refused with a conflict and stores nothing. */
    public function test_units_already_marked_cannot_be_sold(): void
    {
        Sanctum::actingAs($this->warehouse, Role::Bodeguero->abilities());
        $this->postJson($this->markUrl(), ['cantidad' => 6, 'motivo' => 'Lote vencido'])->assertCreated();

        $this->sell(5)
            ->assertConflict()
            ->assertJsonPath('detail', 'No alcanza el stock de Leche entera 1 L. Disponible para vender: 4.');

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame(0, DB::table('ventas')->count());
        $this->assertSame(10, (int) DB::table('productos')->where('id', $this->milk->id)->value('stock'));
    }

    /** Both orders end with the last sellable unit going to exactly one of the two, never to both. */
    public function test_the_last_unit_goes_to_only_one_of_the_sale_and_the_mark(): void
    {
        $this->sell(9)->assertCreated();

        Sanctum::actingAs($this->warehouse, Role::Bodeguero->abilities());
        $this->postJson($this->markUrl(), ['cantidad' => 1, 'motivo' => 'Última unidad'])->assertCreated();

        $this->sell(1)->assertConflict();

        $this->scopeQueriesTo($this->negocio->id);
        $stock = (int) DB::table('productos')->where('id', $this->milk->id)->value('stock');
        $marked = (int) DB::table('unidades_vencidas')->where('estado', 'activa')->sum('cantidad');
        $this->assertSame(1, $stock);
        $this->assertSame(1, $marked);
        $this->assertLessThanOrEqual($stock, $marked, 'More units were marked than the stock holds.');
    }

    /** The product row is read with FOR UPDATE before the units are marked, which serializes it with the sales. */
    public function test_the_product_row_is_locked_before_marking(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });
        Sanctum::actingAs($this->warehouse, Role::Bodeguero->abilities());

        $this->postJson($this->markUrl(), ['cantidad' => 1, 'motivo' => 'Vencida'])->assertCreated();

        $locks = array_filter($queries, fn (string $sql): bool => str_contains($sql, 'from "productos"') && str_contains($sql, 'for update'));
        $this->assertNotEmpty($locks, 'The product row was not locked with FOR UPDATE.');
    }

    /** A cashier sells the given units of the milk. */
    private function sell(int $quantity): TestResponse
    {
        Sanctum::actingAs($this->cashier, Role::Cajero->abilities());

        return $this->postJson('/api/v1/ventas', ['lineas' => [['producto_id' => $this->milk->id, 'cantidad' => $quantity]]]);
    }

    private function markUrl(): string
    {
        return '/api/v1/productos/'.$this->milk->id.'/vencidos';
    }
}
