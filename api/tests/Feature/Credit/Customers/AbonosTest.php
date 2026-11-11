<?php

declare(strict_types=1);

namespace Tests\Feature\Credit\Customers;

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
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
 * US-305: the cashier selects the customer, sees what they owe and receives an abono or the whole
 * balance; the balance never goes negative and the customer gets a receipt.
 */
final class AbonosTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private NegocioModel $negocio;

    private UserModel $cashier;

    private CustomerModel $maria;

    /** At 3:30 p. m., Minisúper Teresita, its cashier and Doña María owing ₡40 000. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-11 15:30:00', 'America/Costa_Rica'));
        $this->negocio = NegocioModel::factory()->create(['nombre' => 'Minisúper Teresita']);
        $this->cashier = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Caja Uno']);
        $this->maria = CustomerModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Doña María']);
        Sanctum::actingAs($this->cashier, Role::Cajero->abilities());
        $this->owes('40000.00');
    }

    /** Selecting the customer shows what they owe. */
    public function test_selecting_the_customer_shows_the_balance(): void
    {
        $this->getJson("/api/v1/clientes/{$this->maria->id}")
            ->assertOk()
            ->assertJsonPath('data.saldo_actual', 40000);
    }

    /** A partial abono lowers the balance and answers the receipt data. */
    public function test_partial_abono_lowers_the_balance(): void
    {
        Event::fake([SaldoFiadoActualizado::class]);

        $response = $this->postJson($this->abonosOf($this->maria), ['monto' => 15000.5]);

        $id = $response->json('data.abono.id');
        $response
            ->assertCreated()
            ->assertJsonPath('data.abono.cliente', ['id' => $this->maria->id, 'nombre' => 'Doña María'])
            ->assertJsonPath('data.abono.monto', 15000.5)
            ->assertJsonPath('data.abono.metodo_pago', 'efectivo')
            ->assertJsonPath('data.abono.saldo_anterior', 40000)
            ->assertJsonPath('data.abono.saldo_restante', 24999.5)
            ->assertJsonPath('data.abono.recibido_por', ['id' => $this->cashier->id, 'nombre' => 'Caja Uno'])
            ->assertJsonPath('data.abono.fecha', '2026-11-11T15:30:00-06:00')
            ->assertJsonPath('data.abono.recibo', "http://localhost/api/v1/abonos/{$id}/recibo")
            ->assertJsonPath('data.cliente.saldo_actual', 24999.5);

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame('24999.50', DB::table('clientes')->where('id', $this->maria->id)->value('saldo_actual'));
        $this->assertDatabaseHas('movimientos_fiado', ['tipo' => 'abono', 'abono_id' => $id, 'monto' => '15000.50', 'usuario_id' => $this->cashier->id]);
        Event::assertDispatched(SaldoFiadoActualizado::class, fn (SaldoFiadoActualizado $event): bool => $event->balance === '24999.50');
    }

    /** Paying the whole balance cancels the debt: the customer owes nothing. */
    public function test_paying_the_whole_balance_cancels_the_debt(): void
    {
        $this->postJson($this->abonosOf($this->maria), ['monto' => 40000])
            ->assertCreated()
            ->assertJsonPath('data.abono.saldo_restante', 0)
            ->assertJsonPath('data.cliente.saldo_actual', 0)
            ->assertJsonPath('data.cliente.disponible', 90000);

        $this->postJson($this->abonosOf($this->maria), ['monto' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('detail', 'Doña María no debe nada, así que no hay nada que abonar.');
    }

    /** An abono larger than the balance is refused: the balance is never negative. */
    public function test_abono_larger_than_the_balance_is_refused(): void
    {
        $this->postJson($this->abonosOf($this->maria), ['monto' => 40000.01])
            ->assertUnprocessable()
            ->assertJsonPath('type', 'http://localhost/problemas/datos-invalidos')
            ->assertJsonPath('detail', 'El abono de ₡40 000,01 es mayor que lo que debe Doña María (₡40 000). Para cancelar todo, el abono es ₡40 000.');

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame('40000.00', DB::table('clientes')->where('id', $this->maria->id)->value('saldo_actual'));
        $this->assertSame(0, DB::table('abonos')->count());
    }

    /** The amount is required and positive; only cash for now. */
    public function test_invalid_abonos_are_refused(): void
    {
        $this->postJson($this->abonosOf($this->maria), [])->assertUnprocessable()->assertJsonPath('errores.monto.0', 'El campo monto es obligatorio.');
        $this->postJson($this->abonosOf($this->maria), ['monto' => 0])->assertUnprocessable();
        $this->postJson($this->abonosOf($this->maria), ['monto' => -5])->assertUnprocessable();
        $this->postJson($this->abonosOf($this->maria), ['monto' => 100, 'metodo_pago' => 'tarjeta'])
            ->assertUnprocessable()
            ->assertJsonPath('detail', 'Por ahora los abonos se reciben en efectivo; "tarjeta" todavía no está disponible.');
    }

    /** A deactivated customer can still pay what they owe. */
    public function test_inactive_customer_can_still_pay(): void
    {
        $this->maria->update(['activo' => false]);

        $this->postJson($this->abonosOf($this->maria), ['monto' => 10000])->assertCreated()->assertJsonPath('data.cliente.saldo_actual', 30000);
    }

    /** The row of the customer is locked before the abono is checked, like in a sale on fiado. */
    public function test_the_customer_row_is_locked_before_the_abono(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->postJson($this->abonosOf($this->maria), ['monto' => 1000])->assertCreated();

        $lock = array_search(true, array_map(fn (string $sql): bool => str_contains($sql, 'from "clientes"') && str_contains($sql, 'for update'), $queries), true);
        $insert = array_search(true, array_map(fn (string $sql): bool => str_contains($sql, 'insert into "abonos"'), $queries), true);
        $this->assertIsInt($lock);
        $this->assertIsInt($insert);
        $this->assertLessThan($insert, $lock);
    }

    /** The receipt is a printable page with the negocio, the customer, the date, the amount and what remains. */
    public function test_receipt_is_a_printable_page(): void
    {
        $id = $this->postJson($this->abonosOf($this->maria), ['monto' => 12500])->json('data.abono.id');

        $response = $this->get("/api/v1/abonos/{$id}/recibo", ['Accept' => 'text/html']);

        $response->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8');
        $response->assertSeeInOrder(['Minisúper Teresita', 'Recibo de abono', '11 de noviembre de 2026, 3:30 p. m.', 'Doña María', 'Caja Uno', 'Efectivo', '₡40 000', '₡12 500', 'Saldo restante', '₡27 500'], false);
    }

    /** The abonos of a customer are listed, the newest first, and each one can be read again. */
    public function test_abonos_of_a_customer_are_listed(): void
    {
        $first = $this->postJson($this->abonosOf($this->maria), ['monto' => 1000])->json('data.abono.id');
        $this->travel(5)->minutes();
        $second = $this->postJson($this->abonosOf($this->maria), ['monto' => 2000])->json('data.abono.id');

        $this->getJson($this->abonosOf($this->maria))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$second, $first])
            ->assertJsonPath('data.0.saldo_restante', 37000)
            ->assertJsonPath('meta.total', 2);

        $this->getJson("/api/v1/abonos/{$first}")->assertOk()->assertJsonPath('data.monto', 1000);
    }

    /** A customer or an abono of another negocio answers 404, and the warehouse keeper cannot receive abonos. */
    public function test_isolation_and_permissions(): void
    {
        $id = $this->postJson($this->abonosOf($this->maria), ['monto' => 1000])->json('data.abono.id');

        $other = NegocioModel::factory()->create();
        Sanctum::actingAs(UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $other->id]), Role::Cajero->abilities());
        $this->postJson($this->abonosOf($this->maria), ['monto' => 1000])->assertNotFound();
        $this->getJson($this->abonosOf($this->maria))->assertNotFound();
        $this->getJson("/api/v1/abonos/{$id}")->assertNotFound();
        $this->get("/api/v1/abonos/{$id}/recibo")->assertNotFound();

        Sanctum::actingAs(UserModel::factory()->role(Role::Bodeguero)->create(['negocio_id' => $this->negocio->id]), Role::Bodeguero->abilities());
        $this->postJson($this->abonosOf($this->maria), ['monto' => 1000])->assertForbidden();
    }

    /** An abono, once recorded, cannot be changed or deleted by the application. */
    public function test_abonos_cannot_be_changed_or_deleted(): void
    {
        $this->postJson($this->abonosOf($this->maria), ['monto' => 1000])->assertCreated();
        $this->scopeQueriesTo($this->negocio->id);

        foreach ([
            fn () => DB::table('abonos')->update(['monto' => '1.00']),
            fn () => DB::table('abonos')->delete(),
        ] as $statement) {
            try {
                DB::transaction($statement);
                $this->fail('The database accepted changing an abono.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(['1000.00'], DB::table('abonos')->pluck('monto')->all());
    }

    /** Route of the abonos of a customer. */
    private function abonosOf(CustomerModel $customer): string
    {
        return "/api/v1/clientes/{$customer->id}/abonos";
    }

    /** Make Doña María owe an amount with a sale on fiado. */
    private function owes(string $amount): void
    {
        $product = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'precio_venta' => $amount, 'stock' => 1]);
        $sale = $this->postJson('/api/v1/ventas', ['lineas' => [['producto_id' => $product->id, 'cantidad' => 1]]])->json('data.id');
        $this->postJson("/api/v1/ventas/{$sale}/fiado", ['cliente_id' => $this->maria->id])->assertOk();
    }
}
