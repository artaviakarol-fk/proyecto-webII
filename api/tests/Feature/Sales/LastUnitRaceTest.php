<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToNegocio;
use Tests\TestCase;

/**
 * Dos cajas venden la misma mercadería. La base de datos serializa las ventas con SELECT ... FOR UPDATE
 * sobre la fila de cada producto, así que el orden de llegada decide quién se queda con la última unidad.
 */
final class LastUnitRaceTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private const string ENDPOINT = '/api/v1/ventas';

    private NegocioModel $negocio;

    private UserModel $cajaUno;

    private UserModel $cajaDos;

    private ProductModel $leche;

    /** A product with one unit left and two cashiers of the same negocio. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->negocio = NegocioModel::factory()->create();
        $this->leche = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Leche entera 1 L', 'precio_venta' => '1200.00', 'stock' => 1]);
        $this->cajaUno = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id]);
        $this->cajaDos = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id]);
    }

    /** Of two registers selling the last unit, one gets it and the other gets a 409 in RFC 9457 format. */
    public function test_two_registers_selling_the_last_unit_one_wins_and_the_other_gets_a_conflict(): void
    {
        Sanctum::actingAs($this->cajaUno, Role::Cajero->abilities());
        $this->postJson(self::ENDPOINT, $this->lines($this->leche, 1))->assertCreated();

        Sanctum::actingAs($this->cajaDos, Role::Cajero->abilities());
        $this->postJson(self::ENDPOINT, $this->lines($this->leche, 1))
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson([
                'type' => 'http://localhost/problemas/conflicto',
                'title' => 'Conflicto',
                'status' => 409,
                'detail' => 'No alcanza el stock de Leche entera 1 L. Disponible para vender: 0.',
            ])
            ->assertJsonStructure(['instance']);

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame(0, (int) DB::table('productos')->where('id', $this->leche->id)->value('stock'));
        $this->assertSame(1, DB::table('ventas')->count());
        $this->assertSame(1, DB::table('movimientos_inventario')->where('tipo', 'venta')->count());
    }

    /** The stock is never negative: the sale that asks for more than what is left is refused as a whole. */
    public function test_stock_never_goes_negative(): void
    {
        Sanctum::actingAs($this->cajaUno, Role::Cajero->abilities());

        $this->postJson(self::ENDPOINT, $this->lines($this->leche, 2))
            ->assertConflict()
            ->assertJsonPath('detail', 'No alcanza el stock de Leche entera 1 L. Disponible para vender: 1.');

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame(1, (int) DB::table('productos')->where('id', $this->leche->id)->value('stock'));
        $this->assertSame(0, DB::table('ventas')->count());
    }

    /** A sale with one line that cannot be served leaves no trace, not even in the lines that could. */
    public function test_failed_sale_leaves_no_trace(): void
    {
        $cafe = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Café', 'stock' => 5]);
        Sanctum::actingAs($this->cajaUno, Role::Cajero->abilities());

        $this->postJson(self::ENDPOINT, ['lineas' => [
            ['producto_id' => $cafe->id, 'cantidad' => 2],
            ['producto_id' => $this->leche->id, 'cantidad' => 3],
        ]])->assertConflict();

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame(5, (int) DB::table('productos')->where('id', $cafe->id)->value('stock'));
        $this->assertSame(0, DB::table('ventas')->count());
        $this->assertSame(0, DB::table('venta_lineas')->count());
        $this->assertSame(0, DB::table('movimientos_inventario')->count());
    }

    /** Revising an open sale cannot take more units than what is left, and the sale keeps its lines. */
    public function test_revising_cannot_take_more_than_what_is_left(): void
    {
        Sanctum::actingAs($this->cajaUno, Role::Cajero->abilities());
        $id = $this->postJson(self::ENDPOINT, $this->lines($this->leche, 1))->json('data.id');

        $this->putJson(self::ENDPOINT.'/'.$id, $this->lines($this->leche, 2))->assertConflict();

        $this->getJson(self::ENDPOINT.'/'.$id)
            ->assertOk()
            ->assertJsonPath('data.lineas.0.cantidad', 1);
        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame(0, (int) DB::table('productos')->where('id', $this->leche->id)->value('stock'));
    }

    /** The product rows are read with FOR UPDATE, which is what serializes the registers in production. */
    public function test_the_product_row_is_locked_before_its_stock_changes(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });
        Sanctum::actingAs($this->cajaUno, Role::Cajero->abilities());

        $this->postJson(self::ENDPOINT, $this->lines($this->leche, 1))->assertCreated();

        $locks = array_filter($queries, fn (string $sql): bool => str_contains($sql, 'from "productos"') && str_contains($sql, 'for update'));
        $this->assertNotEmpty($locks, 'The product row was not locked with FOR UPDATE.');
    }

    /**
     * The body of a sale of one product.
     *
     * @return array{lineas: list<array{producto_id: string, cantidad: int}>}
     */
    private function lines(ProductModel $product, int $quantity): array
    {
        return ['lineas' => [['producto_id' => $product->id, 'cantidad' => $quantity]]];
    }
}
