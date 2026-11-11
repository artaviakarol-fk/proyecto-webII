<?php

declare(strict_types=1);

namespace Tests\Feature\Fiado;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Src\Credit\Customers\Infrastructure\Events\SaldoFiadoActualizado;
use Src\Credit\Customers\Infrastructure\Persistence\Models\CustomerModel;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToNegocio;
use Tests\TestCase;

/**
 * US-303: the cashier closes an open sale on fiado, and it goes to what the customer owes only if
 * it fits the limit. The row of the customer is locked first, so two registers selling on fiado
 * to the same customer are checked against the limit one after the other.
 */
final class LimiteFiadoTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private const string CONFLICT = 'http://localhost/problemas/conflicto';

    private NegocioModel $negocio;

    private UserModel $cajaUno;

    private UserModel $cajaDos;

    private ProductModel $arroz;

    private CustomerModel $maria;

    /** A negocio with two registers, a product of ₡10 000 and Doña María, with a limit of ₡25 000 and owing nothing. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->negocio = NegocioModel::factory()->create();
        $this->cajaUno = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id]);
        $this->cajaDos = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id]);
        $this->arroz = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Arroz 5 kg', 'precio_venta' => '10000.00', 'stock' => 50]);
        $this->maria = CustomerModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Doña María', 'limite_fiado' => '25000.00']);
        Sanctum::actingAs($this->cajaUno, Role::Cajero->abilities());
    }

    /** A sale that fits goes on fiado: the sale is closed with that method and the customer owes its total. */
    public function test_sale_on_fiado_is_charged_to_the_customer(): void
    {
        Event::fake([SaldoFiadoActualizado::class]);
        $sale = $this->openSale(2);

        $this->postJson("/api/v1/ventas/{$sale}/fiado", ['cliente_id' => $this->maria->id])
            ->assertOk()
            ->assertExactJson(['data' => [
                'venta_id' => $sale,
                'metodo_pago' => 'fiado',
                'total' => 20000,
                'cliente' => [
                    'id' => $this->maria->id,
                    'nombre' => 'Doña María',
                    'telefono' => $this->maria->telefono,
                    'limite_fiado' => 25000,
                    'ampliacion_vigente' => 0,
                    'limite_efectivo' => 25000,
                    'saldo_actual' => 20000,
                    'disponible' => 5000,
                    'activo' => true,
                ],
            ]]);

        $this->getJson("/api/v1/ventas/{$sale}")
            ->assertJsonPath('data.estado', 'cobrada')
            ->assertJsonPath('data.metodo_pago', 'fiado');

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseHas('movimientos_fiado', [
            'cliente_id' => $this->maria->id,
            'tipo' => 'cargo',
            'monto' => '20000.00',
            'venta_id' => $sale,
            'usuario_id' => $this->cajaUno->id,
        ]);

        Event::assertDispatched(SaldoFiadoActualizado::class, fn (SaldoFiadoActualizado $event): bool => $event->customerId === $this->maria->id && $event->balance === '20000.00');
    }

    /** If saldo + total passes the limit, the sale is refused with a clear message and nothing changes. */
    public function test_sale_above_the_limit_is_refused_with_a_clear_message(): void
    {
        Event::fake([SaldoFiadoActualizado::class]);
        $this->charge($this->openSale(2));
        $sale = $this->openSale(1);

        $this->postJson("/api/v1/ventas/{$sale}/fiado", ['cliente_id' => $this->maria->id])
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', self::CONFLICT)
            ->assertJsonPath('detail', 'La venta de ₡10 000 no cabe en el fiado de Doña María: debe ₡20 000 de un límite de ₡25 000 y le quedan ₡5 000 disponibles.');

        $this->getJson("/api/v1/ventas/{$sale}")->assertJsonPath('data.estado', 'abierta');
        $this->getJson("/api/v1/clientes/{$this->maria->id}")->assertJsonPath('data.saldo_actual', 20000);
        Event::assertDispatchedTimes(SaldoFiadoActualizado::class, 1);
    }

    /** A sale that exactly reaches the limit fits. */
    public function test_sale_that_reaches_the_limit_exactly_fits(): void
    {
        $this->maria->update(['limite_fiado' => '20000.00']);

        $this->charge($this->openSale(2));

        $this->getJson("/api/v1/clientes/{$this->maria->id}")
            ->assertJsonPath('data.saldo_actual', 20000)
            ->assertJsonPath('data.disponible', 0);
    }

    /**
     * Two registers sell on fiado to Doña María at the same time; each sale fits alone but not both.
     * The lock on the customer makes the second one see the balance the first one left, so it is refused.
     */
    public function test_two_registers_at_the_same_time_cannot_pass_the_limit(): void
    {
        $first = $this->openSale(2);
        Sanctum::actingAs($this->cajaDos, Role::Cajero->abilities());
        $second = $this->openSale(1);

        Sanctum::actingAs($this->cajaUno, Role::Cajero->abilities());
        $this->postJson("/api/v1/ventas/{$first}/fiado", ['cliente_id' => $this->maria->id])->assertOk();

        Sanctum::actingAs($this->cajaDos, Role::Cajero->abilities());
        $this->postJson("/api/v1/ventas/{$second}/fiado", ['cliente_id' => $this->maria->id])->assertConflict();

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame('20000.00', DB::table('clientes')->where('id', $this->maria->id)->value('saldo_actual'));
        $this->assertSame(1, DB::table('movimientos_fiado')->count());
    }

    /** The row of the customer is locked (SELECT ... FOR UPDATE) before the limit is checked. */
    public function test_the_customer_row_is_locked_before_the_limit_is_checked(): void
    {
        $sale = $this->openSale(1);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->postJson("/api/v1/ventas/{$sale}/fiado", ['cliente_id' => $this->maria->id])->assertOk();

        $lock = array_search(true, array_map(fn (string $sql): bool => str_contains($sql, 'from "clientes"') && str_contains($sql, 'for update'), $queries), true);
        $insert = array_search(true, array_map(fn (string $sql): bool => str_contains($sql, 'insert into "movimientos_fiado"'), $queries), true);
        $this->assertIsInt($lock, 'The customer row is never locked.');
        $this->assertIsInt($insert);
        $this->assertLessThan($insert, $lock);
    }

    /** A sale on fiado needs an active customer of the negocio. */
    public function test_sale_on_fiado_needs_an_active_customer_of_the_negocio(): void
    {
        $sale = $this->openSale(1);
        $inactive = CustomerModel::factory()->inactive()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Luis Vargas']);
        $foreign = CustomerModel::factory()->create(['negocio_id' => NegocioModel::factory()->create()->id]);

        $this->postJson("/api/v1/ventas/{$sale}/fiado", [])
            ->assertUnprocessable()
            ->assertJsonPath('errores.cliente_id.0', 'El campo cliente es obligatorio.');

        $this->postJson("/api/v1/ventas/{$sale}/fiado", ['cliente_id' => $inactive->id])
            ->assertConflict()
            ->assertJsonPath('detail', 'Luis Vargas está inactivo y no puede comprar a fiado.');

        $this->postJson("/api/v1/ventas/{$sale}/fiado", ['cliente_id' => $foreign->id])
            ->assertNotFound()
            ->assertJsonPath('detail', 'El cliente no existe.');

        $this->getJson("/api/v1/ventas/{$sale}")->assertJsonPath('data.estado', 'abierta');
    }

    /** Only an open sale goes on fiado, and only once. */
    public function test_only_an_open_sale_goes_on_fiado(): void
    {
        $sale = $this->openSale(1);
        $this->charge($sale);

        $this->postJson("/api/v1/ventas/{$sale}/fiado", ['cliente_id' => $this->maria->id])
            ->assertConflict()
            ->assertJsonPath('detail', 'La venta ya no está abierta, por eso no se puede modificar ni cobrar.');

        $paid = $this->openSale(1);
        $this->postJson("/api/v1/ventas/{$paid}/cobrar")->assertOk();
        $this->postJson("/api/v1/ventas/{$paid}/fiado", ['cliente_id' => $this->maria->id])->assertConflict();

        $this->postJson('/api/v1/ventas/019a0000-7b1e-7c3a-9f1d-2b6a4e8c0d99/fiado', ['cliente_id' => $this->maria->id])->assertNotFound();

        $this->getJson("/api/v1/clientes/{$this->maria->id}")->assertJsonPath('data.saldo_actual', 10000);
    }

    /** Cancelling a sale on fiado gives the units back to the stock and the amount back to the customer. */
    public function test_cancelling_a_sale_on_fiado_lowers_the_debt(): void
    {
        Event::fake([SaldoFiadoActualizado::class]);
        $sale = $this->openSale(2);
        $this->charge($sale);

        $this->postJson("/api/v1/ventas/{$sale}/anular")->assertOk()->assertJsonPath('data.estado', 'anulada');

        $this->getJson("/api/v1/clientes/{$this->maria->id}")
            ->assertJsonPath('data.saldo_actual', 0)
            ->assertJsonPath('data.disponible', 25000);

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame(50, (int) DB::table('productos')->where('id', $this->arroz->id)->value('stock'));
        $this->assertEqualsCanonicalizing(['cargo', 'anulacion'], DB::table('movimientos_fiado')->pluck('tipo')->all());
        Event::assertDispatchedTimes(SaldoFiadoActualizado::class, 2);
    }

    /** Cancelling a sale that was not on fiado does not touch any customer. */
    public function test_cancelling_a_cash_sale_does_not_touch_the_fiado(): void
    {
        $sale = $this->openSale(1);
        $this->postJson("/api/v1/ventas/{$sale}/cobrar")->assertOk();

        $this->postJson("/api/v1/ventas/{$sale}/anular")->assertOk();

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame(0, DB::table('movimientos_fiado')->count());
    }

    /** The warehouse keeper does not sell, on fiado or otherwise. */
    public function test_warehouse_keeper_cannot_sell_on_fiado(): void
    {
        $sale = $this->openSale(1);
        Sanctum::actingAs(UserModel::factory()->role(Role::Bodeguero)->create(['negocio_id' => $this->negocio->id]), Role::Bodeguero->abilities());

        $this->postJson("/api/v1/ventas/{$sale}/fiado", ['cliente_id' => $this->maria->id])->assertForbidden();
    }

    /** Open a sale of some bags of rice at the register that is acting. */
    private function openSale(int $bags): string
    {
        return $this->postJson('/api/v1/ventas', ['lineas' => [['producto_id' => $this->arroz->id, 'cantidad' => $bags]]])
            ->assertCreated()
            ->json('data.id');
    }

    /** Close a sale on fiado to Doña María. */
    private function charge(string $sale): void
    {
        $this->postJson("/api/v1/ventas/{$sale}/fiado", ['cliente_id' => $this->maria->id])->assertOk();
    }
}
