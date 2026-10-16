<?php

declare(strict_types=1);

namespace Tests\Feature\Flujos;

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
 * The whole day of a minisúper through the API, with the account that does each task: the warehouse receives
 * goods, marks the expired units, the register sells, pays and cancels, and the stock tells the truth at every step.
 */
final class RutaCriticaTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private NegocioModel $negocio;

    private ProductModel $milk;

    /** A negocio whose milk has 5 units on the shelf. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->negocio = NegocioModel::factory()->create();
        $this->milk = ProductModel::factory()->create([
            'negocio_id' => $this->negocio->id,
            'codigo' => 'LECHE-1L',
            'nombre' => 'Leche entera 1 L',
            'precio_venta' => '1200.00',
            'costo_actual' => '900.00',
            'stock' => 5,
            'es_perecedero' => true,
        ]);
    }

    /** Receive, mark expired, sell with a warning, pay, and cancel another sale: the stock follows each step. */
    public function test_receive_mark_sell_pay_and_cancel(): void
    {
        $this->actAs(Role::Bodeguero);
        $this->postJson('/api/v1/entradas', [
            'proveedor' => 'Distribuidora Central',
            'fecha' => now()->format('Y-m-d'),
            'lineas' => [['producto_id' => $this->milk->id, 'cantidad' => 10, 'costo_unitario' => 950]],
        ])->assertCreated();
        $this->assertAvailability(stock: 15, expired: 0, sellable: 15);

        $this->postJson($this->url('vencidos'), ['cantidad' => 3, 'motivo' => 'Fecha de vencimiento cumplida'])
            ->assertCreated()
            ->assertJsonPath('data.stock_vendible', 12);

        $this->actAs(Role::Cajero);
        $sale = $this->postJson('/api/v1/ventas', ['lineas' => [['producto_id' => $this->milk->id, 'cantidad' => 4]]])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'abierta')
            ->assertJsonPath('data.advertencias.0.tipo', 'unidades_vencidas')
            ->assertJsonPath('data.advertencias.0.unidades_vencidas', 3)
            ->json('data.id');
        $this->assertAvailability(stock: 11, expired: 3, sellable: 8);

        $this->postJson('/api/v1/ventas/'.$sale.'/cobrar')
            ->assertOk()
            ->assertJsonPath('data.estado', 'cobrada')
            ->assertJsonPath('data.advertencias', []);
        $this->assertAvailability(stock: 11, expired: 3, sellable: 8);

        $cancelled = $this->postJson('/api/v1/ventas', ['lineas' => [['producto_id' => $this->milk->id, 'cantidad' => 2]]])->json('data.id');
        $this->assertAvailability(stock: 9, expired: 3, sellable: 6);

        $this->postJson('/api/v1/ventas/'.$cancelled.'/anular')->assertOk()->assertJsonPath('data.estado', 'anulada');
        $this->assertAvailability(stock: 11, expired: 3, sellable: 8);

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertEqualsCanonicalizing(['cobrada', 'anulada'], DB::table('ventas')->pluck('estado')->all());
    }

    /** The expired units are never sold, even when the cashier asks for the whole stock. */
    public function test_the_cashier_cannot_sell_the_expired_units(): void
    {
        $this->actAs(Role::Bodeguero);
        $this->postJson($this->url('vencidos'), ['cantidad' => 2, 'motivo' => 'Empaque inflado'])->assertCreated();

        $this->actAs(Role::Cajero);
        $this->postJson('/api/v1/ventas', ['lineas' => [['producto_id' => $this->milk->id, 'cantidad' => 5]]])
            ->assertConflict()
            ->assertJsonPath('detail', 'No alcanza el stock de Leche entera 1 L. Disponible para vender: 3.');
        $this->postJson('/api/v1/ventas', ['lineas' => [['producto_id' => $this->milk->id, 'cantidad' => 3]]])->assertCreated();
        $this->assertAvailability(stock: 2, expired: 2, sellable: 0, soldOut: true);
    }

    /** Liquor received into the shop is sold only with the id of the buyer, and then it is paid like any sale. */
    public function test_liquor_is_sold_only_with_the_id_of_the_buyer(): void
    {
        $this->actAs(Role::Bodeguero);
        $wine = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Vino tinto', 'precio_venta' => '5000.00', 'stock' => 0, 'es_licor' => true]);
        $this->postJson('/api/v1/entradas', [
            'proveedor' => 'Licorera Norte',
            'fecha' => now()->format('Y-m-d'),
            'lineas' => [['producto_id' => $wine->id, 'cantidad' => 6, 'costo_unitario' => 3500]],
        ])->assertCreated();

        $this->actAs(Role::Cajero);
        $lines = ['lineas' => [['producto_id' => $wine->id, 'cantidad' => 1]]];
        $this->postJson('/api/v1/ventas', $lines)->assertUnprocessable();
        $this->assertAvailabilityOf($wine, 6);

        $sale = $this->postJson('/api/v1/ventas', [...$lines, 'cedula_comprador' => '111111111'])
            ->assertCreated()
            ->assertJsonPath('data.cedula_comprador', '111111111')
            ->json('data.id');
        $this->postJson('/api/v1/ventas/'.$sale.'/cobrar')->assertOk()->assertJsonPath('data.estado', 'cobrada');
        $this->assertAvailabilityOf($wine, 5);
    }

    /** Authenticate as an account of the negocio with the given role. */
    private function actAs(Role $role): void
    {
        Sanctum::actingAs(UserModel::factory()->role($role)->create(['negocio_id' => $this->negocio->id]), $role->abilities());
    }

    private function url(string $suffix): string
    {
        return '/api/v1/productos/'.$this->milk->id.'/'.$suffix;
    }

    /** The stock of a product, read from its availability endpoint. */
    private function assertAvailabilityOf(ProductModel $product, int $stock): void
    {
        $this->getJson('/api/v1/productos/'.$product->id.'/disponibilidad')->assertOk()->assertJsonPath('data.stock', $stock);
    }

    /** The availability endpoint tells the stock, the expired units and what can still be sold. */
    private function assertAvailability(int $stock, int $expired, int $sellable, bool $soldOut = false): void
    {
        $this->getJson($this->url('disponibilidad'))
            ->assertOk()
            ->assertJsonPath('data.stock', $stock)
            ->assertJsonPath('data.unidades_vencidas', $expired)
            ->assertJsonPath('data.stock_vendible', $sellable)
            ->assertJsonPath('data.agotado', $soldOut);
    }
}
