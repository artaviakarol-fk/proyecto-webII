<?php

declare(strict_types=1);

namespace Tests\Feature\Credit\Customers;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Credit\Customers\Infrastructure\Persistence\Models\CustomerModel;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\Factories\SaleFactory;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToNegocio;
use Tests\TestCase;

/**
 * US-302: saldo_actual only changes through the movements of fiado.
 *
 * The tests run as the application role, the same one the requests use, so they prove what the
 * database itself allows, whatever the code of the API does.
 */
final class FiadoBalanceTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private NegocioModel $negocio;

    private UserModel $cashier;

    private CustomerModel $customer;

    /** A negocio with a cashier and a customer who owes nothing. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->negocio = NegocioModel::factory()->create();
        $this->cashier = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id]);
        $this->customer = CustomerModel::factory()->create(['negocio_id' => $this->negocio->id]);
        $this->scopeQueriesTo($this->negocio->id);
    }

    /** A charge raises the balance and a payment lowers it, in the same statement that records the movement. */
    public function test_movements_move_the_balance(): void
    {
        $this->charge('12500.00');
        $this->assertBalance('12500.00');

        $this->charge('3000.50');
        $this->assertBalance('15500.50');

        $this->payment('5500.50');
        $this->assertBalance('10000.00');
    }

    /** The application role cannot write the balance directly, not even through a plain query. */
    public function test_balance_cannot_be_written_directly(): void
    {
        $this->assertRejected(fn () => DB::table('clientes')->where('id', $this->customer->id)->update(['saldo_actual' => '1000.00']));
        $this->assertRejected(fn () => DB::table('clientes')->insert([
            'id' => (string) Str::uuid7(),
            'negocio_id' => $this->negocio->id,
            'nombre' => 'Con saldo inventado',
            'limite_fiado' => '90000.00',
            'saldo_actual' => '5000.00',
        ]));

        $this->assertBalance('0.00');
    }

    /** A customer is never deleted: they are deactivated, so their movements always have an owner. */
    public function test_customers_cannot_be_deleted(): void
    {
        $this->assertRejected(fn () => DB::table('clientes')->where('id', $this->customer->id)->delete());

        $this->assertSame(1, DB::table('clientes')->where('id', $this->customer->id)->count());
    }

    /** A payment larger than what the customer owes is rejected: the balance is never negative. */
    public function test_balance_never_goes_negative(): void
    {
        $this->charge('1000.00');

        $this->assertRejected(fn () => $this->payment('1000.01'));

        $this->assertBalance('1000.00');
    }

    /** Movements are only added: they cannot be changed or deleted, and the balance stays as it was. */
    public function test_movements_cannot_be_changed_or_deleted(): void
    {
        $this->charge('2000.00');

        $this->assertRejected(fn () => DB::table('movimientos_fiado')->update(['monto' => '1.00']));
        $this->assertRejected(fn () => DB::table('movimientos_fiado')->delete());

        $this->assertSame(['2000.00'], DB::table('movimientos_fiado')->pluck('monto')->all());
        $this->assertBalance('2000.00');
    }

    /** A movement needs a positive amount and its origin: a sale for a charge, a payment for an abono. */
    public function test_movement_needs_a_positive_amount_and_its_origin(): void
    {
        $this->assertRejected(fn () => $this->charge('0.00'));
        $this->assertRejected(fn () => $this->movement(['tipo' => 'cargo', 'monto' => '100.00']));
        $this->assertRejected(fn () => $this->movement(['tipo' => 'abono', 'monto' => '100.00']));
        $this->assertRejected(fn () => $this->movement(['tipo' => 'ajuste', 'monto' => '100.00', 'abono_id' => (string) Str::uuid7()]));

        $this->assertBalance('0.00');
    }

    /** A sale is charged to fiado only once. */
    public function test_sale_is_charged_only_once(): void
    {
        $sale = $this->sale();
        $this->movement(['tipo' => 'cargo', 'monto' => '1500.00', 'venta_id' => $sale]);

        $this->assertRejected(fn () => $this->movement(['tipo' => 'cargo', 'monto' => '1500.00', 'venta_id' => $sale]));

        $this->assertBalance('1500.00');
    }

    /** A movement of another negocio cannot touch the customer: row-level security rejects it. */
    public function test_movement_of_another_negocio_is_rejected(): void
    {
        $other = NegocioModel::factory()->create();
        $this->scopeQueriesTo($other->id);

        $this->assertRejected(fn () => $this->movement(['negocio_id' => $other->id, 'tipo' => 'abono', 'monto' => '1.00', 'abono_id' => (string) Str::uuid7()]));

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertBalance('0.00');
    }

    /** Charge an amount to the customer, as a sale on fiado will do (US-303). */
    private function charge(string $amount): void
    {
        $this->movement(['tipo' => 'cargo', 'monto' => $amount, 'venta_id' => $this->sale()]);
    }

    /** Take an amount from what the customer owes with an abono (US-305): first the abono, then its movement. */
    private function payment(string $amount): void
    {
        $before = (string) DB::table('clientes')->where('id', $this->customer->id)->value('saldo_actual');
        $abono = (string) Str::uuid7();

        DB::table('abonos')->insert([
            'id' => $abono,
            'negocio_id' => $this->negocio->id,
            'cliente_id' => $this->customer->id,
            'monto' => $amount,
            'saldo_anterior' => $before,
            'saldo_restante' => $this->minus($before, $amount),
            'usuario_id' => $this->cashier->id,
        ]);
        $this->movement(['tipo' => 'abono', 'monto' => $amount, 'abono_id' => $abono]);
    }

    /**
     * Insert a movement of the customer with some columns replaced.
     *
     * @param  array<string, string>  $columns
     */
    private function movement(array $columns): void
    {
        DB::table('movimientos_fiado')->insert([
            'id' => (string) Str::uuid7(),
            'negocio_id' => $this->negocio->id,
            'cliente_id' => $this->customer->id,
            'usuario_id' => $this->cashier->id,
            ...$columns,
        ]);
    }

    /** Subtract two decimal amounts, even below zero, so the database is the one that refuses a negative balance. */
    private function minus(string $from, string $amount): string
    {
        $cents = (int) round((float) $from * 100) - (int) round((float) $amount * 100);

        return sprintf('%s%d.%02d', $cents < 0 ? '-' : '', intdiv(abs($cents), 100), abs($cents) % 100);
    }

    /** An open sale of the negocio, to point a charge to. */
    private function sale(): string
    {
        $product = ProductModel::factory()->create(['negocio_id' => $this->negocio->id]);

        $sale = SaleFactory::new()->for($this->negocio, $this->cashier)->line($product, 1)->create();
        $this->scopeQueriesTo($this->negocio->id);

        return $sale;
    }

    /** The database refuses the statement; it runs in a savepoint so the test can go on. */
    private function assertRejected(callable $statement): void
    {
        try {
            DB::transaction(fn () => $statement());
        } catch (QueryException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('The database accepted a statement it should reject.');
    }

    /** What the customer owes, as stored. */
    private function assertBalance(string $expected): void
    {
        $this->assertSame($expected, DB::table('clientes')->where('id', $this->customer->id)->value('saldo_actual'));
    }
}
