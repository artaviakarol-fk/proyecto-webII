<?php

declare(strict_types=1);

namespace Tests\Feature\Inventario;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Domain\ValueObjects\Stock;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToNegocio;
use Tests\TestCase;

final class EntradaMercaderiaTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private const string ENDPOINT = '/api/v1/entradas';

    private NegocioModel $negocio;

    private NegocioModel $otherNegocio;

    private UserModel $bodeguero;

    private ProductModel $arroz;

    /** Two negocios, a product with 20 units and the warehouse keeper of the first negocio, on a fixed day. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-15 09:30:00');
        $this->negocio = NegocioModel::factory()->create();
        $this->otherNegocio = NegocioModel::factory()->create();
        $this->arroz = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Arroz 1 kg', 'codigo' => 'ARROZ-1', 'stock' => 20, 'costo_actual' => '1000.00']);
        $this->bodeguero = UserModel::factory()->role(Role::Bodeguero)->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Luis Bodega']);
        Sanctum::actingAs($this->bodeguero, Role::Bodeguero->abilities());
    }

    /** A receipt adds its units to the stock, updates the cost and records who registered it and when. */
    public function test_receipt_adds_to_the_stock_and_records_who_and_when(): void
    {
        $response = $this->postJson(self::ENDPOINT, $this->receipt([
            ['producto_id' => $this->arroz->id, 'cantidad' => 12, 'costo_unitario' => 1050.5],
        ]));

        $id = $response->json('data.id');
        $response
            ->assertCreated()
            ->assertHeader('Location', 'http://localhost'.self::ENDPOINT.'/'.$id)
            ->assertExactJson(['data' => [
                'id' => $id,
                'proveedor' => 'Distribuidora Central',
                'fecha' => '2026-10-15',
                'registrada_por' => ['id' => $this->bodeguero->id, 'nombre' => 'Luis Bodega'],
                'registrada_en' => '2026-10-15T09:30:00+00:00',
                'lineas' => [[
                    'producto_id' => $this->arroz->id,
                    'codigo' => 'ARROZ-1',
                    'nombre' => 'Arroz 1 kg',
                    'cantidad' => 12,
                    'costo_unitario' => 1050.5,
                    'subtotal' => 12606,
                    'costo_anterior' => 1000,
                    'diferencia_costo' => 50.5,
                    'cambio_costo' => true,
                    'precio_venta' => 1500,
                ]],
                'total' => 12606,
            ]]);

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseHas('productos', ['id' => $this->arroz->id, 'stock' => 32, 'costo_actual' => '1050.50']);
        $this->assertDatabaseHas('movimientos_inventario', [
            'producto_id' => $this->arroz->id,
            'tipo' => 'entrada',
            'cantidad' => 12,
            'stock_resultante' => 32,
            'referencia_id' => $id,
            'usuario_id' => $this->bodeguero->id,
        ]);
    }

    /** A new product is created from the same receipt, with the stock and the cost of its line. */
    public function test_new_product_is_created_from_the_receipt(): void
    {
        $response = $this->postJson(self::ENDPOINT, $this->receipt([
            ['producto' => ['codigo' => 'LECHE-1L', 'nombre' => 'Leche entera 1 L', 'precio_venta' => 950, 'categoria' => 'Lácteos', 'es_perecedero' => true], 'cantidad' => 24, 'costo_unitario' => 760],
            ['producto_id' => $this->arroz->id, 'cantidad' => 5, 'costo_unitario' => 1000],
        ]))->assertCreated();

        $newId = $response->json('data.lineas.0.producto_id');
        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseHas('productos', [
            'id' => $newId,
            'negocio_id' => $this->negocio->id,
            'codigo' => 'LECHE-1L',
            'stock' => 24,
            'costo_actual' => '760.00',
            'precio_venta' => '950.00',
            'es_perecedero' => true,
        ]);
        $this->assertDatabaseHas('productos', ['id' => $this->arroz->id, 'stock' => 25]);
        $this->assertEquals(23240, $response->json('data.total'));
    }

    /** The same product twice in one receipt adds both lines, one after the other. */
    public function test_same_product_twice_adds_both_lines(): void
    {
        $this->postJson(self::ENDPOINT, $this->receipt([
            ['producto_id' => $this->arroz->id, 'cantidad' => 3, 'costo_unitario' => 1000],
            ['producto_id' => $this->arroz->id, 'cantidad' => 7, 'costo_unitario' => 1000],
        ]))->assertCreated();

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame(30, DB::table('productos')->where('id', $this->arroz->id)->value('stock'));
        $this->assertSame([23, 30], DB::table('movimientos_inventario')->orderBy('stock_resultante')->pluck('stock_resultante')->all());
    }

    /** If one line fails nothing is stored: no receipt, no new product and the stock stays as it was. */
    public function test_a_failing_line_stores_nothing(): void
    {
        $this->postJson(self::ENDPOINT, $this->receipt([
            ['producto' => ['codigo' => 'PAN-1', 'nombre' => 'Pan', 'precio_venta' => 1200], 'cantidad' => 10, 'costo_unitario' => 800],
            ['producto_id' => $this->arroz->id, 'cantidad' => 5, 'costo_unitario' => 1000],
            ['producto_id' => $this->arroz->id, 'cantidad' => Stock::MAX, 'costo_unitario' => 1000],
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('detail', 'La entrada dejaría un producto con más de 1000000 unidades en stock.');

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseCount('entradas_mercaderia', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertDatabaseMissing('productos', ['codigo' => 'PAN-1']);
        $this->assertDatabaseHas('productos', ['id' => $this->arroz->id, 'stock' => 20]);
    }

    /** Invalid receipts are rejected in RFC 9457 format, field by field. */
    #[DataProvider('invalidReceipts')]
    public function test_invalid_receipt_is_rejected(array $receipt, array $line, string $field, string $message): void
    {
        $payload = array_replace(
            $this->receipt([array_replace(['producto_id' => $this->arroz->id, 'cantidad' => 5, 'costo_unitario' => 1000], $line)]),
            $receipt,
        );

        $response = $this->postJson(self::ENDPOINT, $payload)
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json');

        $this->assertSame($message, $response->json('errores')[$field][0] ?? null);
    }

    /**
     * Changes to the receipt and to its line that make it invalid, the field reported and its message.
     *
     * @return array<string, array{array<string, mixed>, array<string, mixed>, string, string}>
     */
    public static function invalidReceipts(): array
    {
        return [
            'cantidad cero' => [[], ['cantidad' => 0], 'lineas.0.cantidad', 'El campo cantidad debe ser al menos 1.'],
            'cantidad negativa' => [[], ['cantidad' => -4], 'lineas.0.cantidad', 'El campo cantidad debe ser al menos 1.'],
            'costo negativo' => [[], ['costo_unitario' => -1], 'lineas.0.costo_unitario', 'El campo costo unitario debe ser al menos 0.'],
            'sin proveedor' => [['proveedor' => ''], [], 'proveedor', 'El campo proveedor es obligatorio.'],
            'fecha futura' => [['fecha' => '2026-10-16'], [], 'fecha', 'El campo fecha debe ser una fecha anterior o igual a today.'],
            'sin líneas' => [['lineas' => []], [], 'lineas', 'El campo líneas es obligatorio.'],
            'producto inexistente' => [[], ['producto_id' => '019a0000-7b1e-7c3a-9f1d-2b6a4e8c0d99'], 'lineas.0.producto_id', 'El valor seleccionado para producto no es válido.'],
            'producto y producto nuevo a la vez' => [[], ['producto' => ['codigo' => 'X', 'nombre' => 'X', 'precio_venta' => 1]], 'lineas.0.producto_id', 'El campo producto impide que producto nuevo esté presente.'],
        ];
    }

    /** A new product cannot repeat the code of a product of the catalog. */
    public function test_new_product_cannot_repeat_a_code(): void
    {
        $this->postJson(self::ENDPOINT, $this->receipt([
            ['producto' => ['codigo' => 'ARROZ-1', 'nombre' => 'Arroz otra marca', 'precio_venta' => 1400], 'cantidad' => 1, 'costo_unitario' => 1000],
        ]))
            ->assertUnprocessable()
            ->assertJson(['errores' => ['lineas.0.producto.codigo' => ['El valor del campo código del producto nuevo ya está en uso.']]]);
    }

    /** A product of another negocio is rejected like a missing one, and its stock stays as it was. */
    public function test_product_of_another_negocio_is_rejected_like_a_missing_one(): void
    {
        $foreign = ProductModel::factory()->create(['negocio_id' => $this->otherNegocio->id, 'stock' => 5]);

        $foreignResponse = $this->postJson(self::ENDPOINT, $this->receipt([['producto_id' => $foreign->id, 'cantidad' => 10, 'costo_unitario' => 100]]));
        $missingResponse = $this->postJson(self::ENDPOINT, $this->receipt([['producto_id' => '019a0000-7b1e-7c3a-9f1d-2b6a4e8c0d99', 'cantidad' => 10, 'costo_unitario' => 100]]));

        $foreignResponse->assertUnprocessable();
        $this->assertSame(Arr::except($missingResponse->json(), 'instance'), Arr::except($foreignResponse->json(), 'instance'));

        $this->scopeQueriesTo($this->otherNegocio->id);
        $this->assertSame(5, DB::table('productos')->where('id', $foreign->id)->value('stock'));
    }

    /** A stored receipt is shown again; one of another negocio answers 404. */
    public function test_receipt_is_shown_and_isolated(): void
    {
        $id = $this->postJson(self::ENDPOINT, $this->receipt([['producto_id' => $this->arroz->id, 'cantidad' => 1, 'costo_unitario' => 1000]]))->json('data.id');

        $this->getJson(self::ENDPOINT.'/'.$id)->assertOk()->assertJsonPath('data.lineas.0.cantidad', 1);

        Sanctum::actingAs(UserModel::factory()->create(['negocio_id' => $this->otherNegocio->id]), Role::Administrador->abilities());
        $this->getJson(self::ENDPOINT.'/'.$id)->assertNotFound();
    }

    /** The administrator also registers receipts; the cashier cannot. */
    public function test_only_staff_with_inventory_ability_registers_receipts(): void
    {
        Sanctum::actingAs(UserModel::factory()->create(['negocio_id' => $this->negocio->id]), Role::Administrador->abilities());
        $this->postJson(self::ENDPOINT, $this->receipt([['producto_id' => $this->arroz->id, 'cantidad' => 1, 'costo_unitario' => 1000]]))->assertCreated();

        Sanctum::actingAs(UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id]), Role::Cajero->abilities());
        $this->postJson(self::ENDPOINT, $this->receipt([['producto_id' => $this->arroz->id, 'cantidad' => 1, 'costo_unitario' => 1000]]))
            ->assertForbidden()
            ->assertJsonPath('type', 'http://localhost/problemas/prohibido');
    }

    /**
     * A valid receipt with the given lines.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private function receipt(array $lines): array
    {
        return ['proveedor' => 'Distribuidora Central', 'fecha' => '2026-10-15', 'lineas' => $lines];
    }
}
