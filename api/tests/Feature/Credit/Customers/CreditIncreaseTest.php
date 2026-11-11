<?php

declare(strict_types=1);

namespace Tests\Feature\Credit\Customers;

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
 * US-304: the cashier increases the limit of a customer in an emergency, with a reason and until
 * the end of the day. The base limit plus the increases in force never pass ₡120 000, every increase
 * is audited, and the administration reviews and reverts them.
 */
final class CreditIncreaseTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private NegocioModel $negocio;

    private UserModel $cashier;

    private UserModel $admin;

    private CustomerModel $maria;

    /** At 3:30 p. m. in Costa Rica, a cashier, an administrator and Doña María with the base limit and owing ₡85 000. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-11 15:30:00', 'America/Costa_Rica'));
        $this->negocio = NegocioModel::factory()->create();
        $this->cashier = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Caja Uno']);
        $this->admin = UserModel::factory()->role(Role::Administrador)->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Doña Teresa']);
        $this->maria = CustomerModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Doña María']);
        $this->owes('85000.00');
        $this->actAs($this->cashier);
    }

    /** The cashier increases the limit with a reason; it is audited and lasts until midnight. */
    public function test_cashier_increases_the_limit_with_a_reason(): void
    {
        $response = $this->postJson($this->increasesOf($this->maria), ['monto' => 20000, 'motivo' => 'Compra de medicinas para el nieto']);

        $response
            ->assertCreated()
            ->assertJsonPath('data.ampliacion.monto', 20000)
            ->assertJsonPath('data.ampliacion.motivo', 'Compra de medicinas para el nieto')
            ->assertJsonPath('data.ampliacion.otorgada_por', ['id' => $this->cashier->id, 'nombre' => 'Caja Uno'])
            ->assertJsonPath('data.ampliacion.otorgada_at', '2026-11-11T15:30:00-06:00')
            ->assertJsonPath('data.ampliacion.vigente_hasta', '2026-11-12T00:00:00-06:00')
            ->assertJsonPath('data.ampliacion.vigente', true)
            ->assertJsonPath('data.ampliacion.revertida_at', null)
            ->assertJsonPath('data.cliente.limite_fiado', 90000)
            ->assertJsonPath('data.cliente.ampliacion_vigente', 20000)
            ->assertJsonPath('data.cliente.limite_efectivo', 110000)
            ->assertJsonPath('data.cliente.disponible', 25000);

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseHas('ampliaciones_limite', [
            'cliente_id' => $this->maria->id,
            'monto' => '20000.00',
            'motivo' => 'Compra de medicinas para el nieto',
            'usuario_id' => $this->cashier->id,
        ]);
        $this->assertSame('90000.00', DB::table('clientes')->where('id', $this->maria->id)->value('limite_fiado'), 'The base limit never changes.');
    }

    /** With the increase, a sale that did not fit goes on fiado. */
    public function test_increase_lets_a_sale_on_fiado_fit(): void
    {
        $sale = $this->openSale('10000.00');

        $this->postJson("/api/v1/ventas/{$sale}/fiado", ['cliente_id' => $this->maria->id])->assertConflict();

        $this->postJson($this->increasesOf($this->maria), ['monto' => 5000, 'motivo' => 'Emergencia'])->assertCreated();

        $this->postJson("/api/v1/ventas/{$sale}/fiado", ['cliente_id' => $this->maria->id])
            ->assertOk()
            ->assertJsonPath('data.cliente.saldo_actual', 95000)
            ->assertJsonPath('data.cliente.disponible', 0);
    }

    /** Increases add up, but the base plus all of them never pass ₡120 000. */
    public function test_increases_add_up_to_the_maximum_and_no_further(): void
    {
        $this->postJson($this->increasesOf($this->maria), ['monto' => 20000, 'motivo' => 'Primera'])->assertCreated();
        $this->postJson($this->increasesOf($this->maria), ['monto' => 10000, 'motivo' => 'Segunda'])
            ->assertCreated()
            ->assertJsonPath('data.cliente.limite_efectivo', 120000);

        $this->postJson($this->increasesOf($this->maria), ['monto' => 1, 'motivo' => 'Tercera'])
            ->assertUnprocessable()
            ->assertJsonPath('detail', 'La ampliación de ₡1 dejaría el límite de Doña María en ₡120 001, y el máximo es ₡120 000. Se le puede ampliar hasta ₡0.');

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame(2, DB::table('ampliaciones_limite')->count());
    }

    /** The maximum comes from the configuration (LIMITE_FIADO_MAXIMO). */
    public function test_maximum_is_configurable(): void
    {
        config(['credit.max_limit' => '100000']);

        $this->postJson($this->increasesOf($this->maria), ['monto' => 10001, 'motivo' => 'Emergencia'])
            ->assertUnprocessable()
            ->assertJsonPath('detail', 'La ampliación de ₡10 001 dejaría el límite de Doña María en ₡100 001, y el máximo es ₡100 000. Se le puede ampliar hasta ₡10 000.');
    }

    /** The increase stops counting at midnight in Costa Rica; the audit stays. */
    public function test_increase_expires_at_midnight(): void
    {
        $this->postJson($this->increasesOf($this->maria), ['monto' => 20000, 'motivo' => 'Emergencia'])->assertCreated();

        $this->travelTo(Carbon::parse('2026-11-11 23:59:59', 'America/Costa_Rica'));
        $this->getJson("/api/v1/clientes/{$this->maria->id}")->assertJsonPath('data.limite_efectivo', 110000);

        $this->travelTo(Carbon::parse('2026-11-12 00:00:00', 'America/Costa_Rica'));
        $this->getJson("/api/v1/clientes/{$this->maria->id}")
            ->assertJsonPath('data.ampliacion_vigente', 0)
            ->assertJsonPath('data.limite_efectivo', 90000)
            ->assertJsonPath('data.disponible', 5000);

        $this->actAs($this->admin);
        $this->getJson('/api/v1/ampliaciones-limite')->assertJsonPath('data.0.vigente', false)->assertJsonPath('meta.total', 1);
    }

    /** A reason and a positive amount are required, and an inactive or foreign customer gets nothing. */
    public function test_invalid_increases_are_refused(): void
    {
        $this->postJson($this->increasesOf($this->maria), ['monto' => 5000])
            ->assertUnprocessable()
            ->assertJsonPath('errores.motivo.0', 'El campo motivo es obligatorio.');
        $this->postJson($this->increasesOf($this->maria), ['monto' => 0, 'motivo' => 'Emergencia'])->assertUnprocessable();
        $this->postJson($this->increasesOf($this->maria), ['monto' => 1000, 'motivo' => '   '])->assertUnprocessable();

        $inactive = CustomerModel::factory()->inactive()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Luis Vargas']);
        $this->postJson($this->increasesOf($inactive), ['monto' => 1000, 'motivo' => 'Emergencia'])
            ->assertConflict()
            ->assertJsonPath('detail', 'Luis Vargas está inactivo y no puede comprar a fiado.');

        $foreign = CustomerModel::factory()->create(['negocio_id' => NegocioModel::factory()->create()->id]);
        $this->postJson($this->increasesOf($foreign), ['monto' => 1000, 'motivo' => 'Emergencia'])->assertNotFound();

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame(0, DB::table('ampliaciones_limite')->count());
    }

    /** The administrator sees every increase, the newest first, and can filter by customer or only those in force. */
    public function test_administrator_reviews_the_increases(): void
    {
        $ana = CustomerModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Ana']);
        $this->postJson($this->increasesOf($this->maria), ['monto' => 5000, 'motivo' => 'Primera'])->assertCreated();
        $this->travel(1)->minutes();
        $this->postJson($this->increasesOf($ana), ['monto' => 3000, 'motivo' => 'De Ana'])->assertCreated();

        $this->actAs($this->admin);
        $this->getJson('/api/v1/ampliaciones-limite')
            ->assertOk()
            ->assertJsonPath('data.*.motivo', ['De Ana', 'Primera'])
            ->assertJsonPath('data.0.cliente', ['id' => $ana->id, 'nombre' => 'Ana'])
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/v1/ampliaciones-limite?cliente_id='.$this->maria->id)->assertJsonPath('data.*.motivo', ['Primera']);
    }

    /** The administrator reverts an increase: it stops counting, the base limit stays and the record keeps who and when. */
    public function test_administrator_reverts_an_increase(): void
    {
        $id = $this->postJson($this->increasesOf($this->maria), ['monto' => 20000, 'motivo' => 'Emergencia'])->json('data.ampliacion.id');
        $this->travel(10)->minutes();

        $this->actAs($this->admin);
        $this->postJson("/api/v1/ampliaciones-limite/{$id}/revertir")
            ->assertOk()
            ->assertJsonPath('data.vigente', false)
            ->assertJsonPath('data.revertida_at', '2026-11-11T15:40:00-06:00')
            ->assertJsonPath('data.revertida_por', ['id' => $this->admin->id, 'nombre' => 'Doña Teresa']);

        $this->getJson("/api/v1/clientes/{$this->maria->id}")
            ->assertJsonPath('data.limite_efectivo', 90000)
            ->assertJsonPath('data.limite_fiado', 90000);
        $this->getJson('/api/v1/ampliaciones-limite?vigentes=1')->assertJsonPath('meta.total', 0);

        $this->postJson("/api/v1/ampliaciones-limite/{$id}/revertir")
            ->assertConflict()
            ->assertJsonPath('detail', 'La ampliación ya fue revertida.');
    }

    /** An expired increase cannot be reverted, and one of another negocio is not found. */
    public function test_expired_or_foreign_increases_cannot_be_reverted(): void
    {
        $id = $this->postJson($this->increasesOf($this->maria), ['monto' => 1000, 'motivo' => 'Emergencia'])->json('data.ampliacion.id');

        $this->travelTo(Carbon::parse('2026-11-12 08:00:00', 'America/Costa_Rica'));
        $this->actAs($this->admin);
        $this->postJson("/api/v1/ampliaciones-limite/{$id}/revertir")
            ->assertConflict()
            ->assertJsonPath('detail', 'La ampliación ya venció, así que no hay nada que revertir.');

        $other = NegocioModel::factory()->create();
        Sanctum::actingAs(UserModel::factory()->role(Role::Administrador)->create(['negocio_id' => $other->id]), Role::Administrador->abilities());
        $this->postJson("/api/v1/ampliaciones-limite/{$id}/revertir")->assertNotFound();
        $this->getJson('/api/v1/ampliaciones-limite')->assertJsonPath('meta.total', 0);
    }

    /** The cashier gives increases but neither reviews nor reverts them; the warehouse keeper does none of it. */
    public function test_permissions(): void
    {
        $this->getJson('/api/v1/ampliaciones-limite')->assertForbidden();
        $this->postJson('/api/v1/ampliaciones-limite/019a0000-7b1e-7c3a-9f1d-2b6a4e8c0d99/revertir')->assertForbidden();

        Sanctum::actingAs(UserModel::factory()->role(Role::Bodeguero)->create(['negocio_id' => $this->negocio->id]), Role::Bodeguero->abilities());
        $this->postJson($this->increasesOf($this->maria), ['monto' => 1000, 'motivo' => 'Emergencia'])->assertForbidden();
    }

    /** The audit cannot be erased: the application role cannot delete an increase. */
    public function test_increases_cannot_be_deleted(): void
    {
        $this->postJson($this->increasesOf($this->maria), ['monto' => 1000, 'motivo' => 'Emergencia'])->assertCreated();
        $this->scopeQueriesTo($this->negocio->id);

        try {
            DB::transaction(fn () => DB::table('ampliaciones_limite')->delete());
            $this->fail('The database accepted deleting the audit.');
        } catch (QueryException) {
            $this->assertSame(1, DB::table('ampliaciones_limite')->count());
        }
    }

    /** Route of the increases of a customer. */
    private function increasesOf(CustomerModel $customer): string
    {
        return "/api/v1/clientes/{$customer->id}/ampliaciones";
    }

    /** Act as an account with the abilities of its role. */
    private function actAs(UserModel $user): void
    {
        Sanctum::actingAs($user, Role::from($user->rol)->abilities());
    }

    /** Make Doña María owe an amount through a charge, as a sale on fiado would. */
    private function owes(string $amount): void
    {
        $product = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'precio_venta' => $amount, 'stock' => 1]);
        $this->actAs($this->cashier);
        $sale = $this->postJson('/api/v1/ventas', ['lineas' => [['producto_id' => $product->id, 'cantidad' => 1]]])->json('data.id');
        $this->postJson("/api/v1/ventas/{$sale}/fiado", ['cliente_id' => $this->maria->id])->assertOk();
    }

    /** Open a sale of one product with a price. */
    private function openSale(string $price): string
    {
        $product = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'precio_venta' => $price, 'stock' => 1]);

        return $this->postJson('/api/v1/ventas', ['lineas' => [['producto_id' => $product->id, 'cantidad' => 1]]])->json('data.id');
    }
}
