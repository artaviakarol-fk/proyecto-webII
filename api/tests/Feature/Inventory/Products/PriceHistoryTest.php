<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory\Products;

use Illuminate\Support\Facades\DB;
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

final class PriceHistoryTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private NegocioModel $negocio;

    private UserModel $admin;

    private ProductModel $arroz;

    /** A product that costs 1000 and sells at 1500, and the administrator of its negocio. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-15 08:00:00');
        $this->negocio = NegocioModel::factory()->create();
        $this->arroz = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'costo_actual' => '1000.00', 'precio_venta' => '1500.00']);
        $this->admin = UserModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Ana Admin']);
        $this->actAs($this->admin, Role::Administrador);
    }

    /** A receipt with a different cost shows the previous cost, the new one and the difference, without touching the price. */
    #[DataProvider('costs')]
    public function test_receipt_compares_the_previous_and_the_new_cost(float $newCost, float $difference, bool $changed): void
    {
        $line = $this->receive($newCost)->json('data.lineas.0');

        $this->assertEquals(1000, $line['costo_anterior']);
        $this->assertEquals($newCost, $line['costo_unitario']);
        $this->assertEquals($difference, $line['diferencia_costo']);
        $this->assertSame($changed, $line['cambio_costo']);
        $this->assertEquals(1500, $line['precio_venta']);

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame('1500.00', DB::table('productos')->where('id', $this->arroz->id)->value('precio_venta'));
    }

    /**
     * New costs, their difference with the previous one and whether the cost changed.
     *
     * @return array<string, array{float, float, bool}>
     */
    public static function costs(): array
    {
        return [
            'sube' => [1150.0, 150.0, true],
            'baja' => [900.5, -99.5, true],
            'igual' => [1000.0, 0.0, false],
        ];
    }

    /** A product created from a receipt has no previous cost to compare. */
    public function test_new_product_has_no_previous_cost(): void
    {
        $line = $this->postJson('/api/v1/entradas', [
            'proveedor' => 'Distribuidora Central',
            'fecha' => '2026-10-15',
            'lineas' => [['producto' => ['codigo' => 'NUEVO-1', 'nombre' => 'Nuevo', 'precio_venta' => 500], 'cantidad' => 1, 'costo_unitario' => 300]],
        ])->assertCreated()->json('data.lineas.0');

        $this->assertNull($line['costo_anterior']);
        $this->assertNull($line['diferencia_costo']);
        $this->assertFalse($line['cambio_costo']);
    }

    /** After seeing the difference the administrator accepts a new sale price. */
    public function test_administrator_accepts_a_new_sale_price(): void
    {
        $this->receive(1150);

        $this->patchJson($this->url('precio'), ['precio_venta' => 1700])
            ->assertOk()
            ->assertJsonPath('data.precio_venta', 1700)
            ->assertJsonPath('data.costo_actual', 1150);
    }

    /** The history keeps every cost and price with the reason, who and when, newest first. */
    public function test_history_records_receipts_price_changes_and_edits(): void
    {
        $receiptId = $this->receive(1150)->json('data.id');
        $this->travelTo('2026-10-15 09:00:00');
        $this->patchJson($this->url('precio'), ['precio_venta' => 1700])->assertOk();
        $this->travelTo('2026-10-15 10:00:00');
        $this->putJson('/api/v1/productos/'.$this->arroz->id, [
            'codigo' => $this->arroz->codigo,
            'nombre' => $this->arroz->nombre,
            'precio_venta' => 1650,
            'costo_actual' => 1150,
            'stock' => 32,
        ])->assertOk();

        $this->getJson($this->url('historial-precios'))
            ->assertOk()
            ->assertExactJson(['data' => [
                $this->entry('2026-10-15T10:00:00-06:00', 1150, 1650, 'edicion', null),
                $this->entry('2026-10-15T09:00:00-06:00', 1150, 1700, 'cambio_precio', null),
                $this->entry('2026-10-15T08:00:00-06:00', 1150, 1500, 'entrada', $receiptId),
            ]]);
    }

    /** A product created through the API starts its history; saving it without price changes adds nothing. */
    public function test_creation_starts_the_history_and_unchanged_saves_add_nothing(): void
    {
        $payload = ['codigo' => 'CAFE-1', 'nombre' => 'Café', 'precio_venta' => 2500, 'costo_actual' => 1800];
        $id = $this->postJson('/api/v1/productos', $payload)->assertCreated()->json('data.id');
        $this->putJson('/api/v1/productos/'.$id, [...$payload, 'nombre' => 'Café molido'])->assertOk();

        $this->getJson("/api/v1/productos/{$id}/historial-precios")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.motivo', 'creacion')
            ->assertJsonPath('data.0.precio_venta', 2500);
    }

    /** Only the administration changes prices or reads their history; the price stays as it was. */
    #[DataProvider('staffWithoutAdministration')]
    public function test_only_the_administration_changes_prices(Role $role): void
    {
        $this->actAs(UserModel::factory()->role($role)->create(['negocio_id' => $this->negocio->id]), $role);

        $this->patchJson($this->url('precio'), ['precio_venta' => 1])->assertForbidden()->assertJsonPath('type', 'http://localhost/problemas/prohibido');
        $this->getJson($this->url('historial-precios'))->assertForbidden();

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame('1500.00', DB::table('productos')->where('id', $this->arroz->id)->value('precio_venta'));
    }

    /**
     * Roles without the administration ability.
     *
     * @return array<string, array{Role}>
     */
    public static function staffWithoutAdministration(): array
    {
        return ['cajero' => [Role::Cajero], 'bodeguero' => [Role::Bodeguero]];
    }

    /** A sale price of zero is rejected. */
    public function test_sale_price_must_be_positive(): void
    {
        $this->patchJson($this->url('precio'), ['precio_venta' => 0])
            ->assertUnprocessable()
            ->assertJsonPath('errores.precio_venta.0', 'El campo precio de venta debe ser mayor que 0.');
    }

    /** The price and the history of a product of another negocio answer 404. */
    public function test_product_of_another_negocio_answers_not_found(): void
    {
        $other = NegocioModel::factory()->create();
        $foreign = ProductModel::factory()->create(['negocio_id' => $other->id, 'precio_venta' => '800.00']);

        $this->patchJson("/api/v1/productos/{$foreign->id}/precio", ['precio_venta' => 1])->assertNotFound();
        $this->getJson("/api/v1/productos/{$foreign->id}/historial-precios")->assertNotFound();

        $this->scopeQueriesTo($other->id);
        $this->assertSame('800.00', DB::table('productos')->where('id', $foreign->id)->value('precio_venta'));
    }

    /** Receive 10 units of the product at a unit cost, as the administrator. */
    private function receive(float $unitCost): TestResponse
    {
        return $this->postJson('/api/v1/entradas', [
            'proveedor' => 'Distribuidora Central',
            'fecha' => '2026-10-15',
            'lineas' => [['producto_id' => $this->arroz->id, 'cantidad' => 10, 'costo_unitario' => $unitCost]],
        ])->assertCreated();
    }

    /**
     * One expected row of the history.
     *
     * @return array<string, mixed>
     */
    private function entry(string $date, int $cost, int $price, string $reason, ?string $reference): array
    {
        return [
            'fecha' => $date,
            'costo' => $cost,
            'precio_venta' => $price,
            'motivo' => $reason,
            'usuario' => ['id' => $this->admin->id, 'nombre' => 'Ana Admin'],
            'referencia_id' => $reference,
        ];
    }

    /** Route of a price endpoint of the product. */
    private function url(string $suffix): string
    {
        return "/api/v1/productos/{$this->arroz->id}/{$suffix}";
    }

    /** Authenticate an account with the abilities of a role. */
    private function actAs(UserModel $user, Role $role): void
    {
        Sanctum::actingAs($user, $role->abilities());
    }
}
