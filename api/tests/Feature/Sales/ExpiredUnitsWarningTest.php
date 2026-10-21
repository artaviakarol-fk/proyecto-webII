<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToNegocio;
use Tests\TestCase;

/** La caja avisa cuando un producto de la venta tiene unidades vencidas en el estante, pero la venta sigue. */
final class ExpiredUnitsWarningTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private const string ENDPOINT = '/api/v1/ventas';

    private NegocioModel $negocio;

    private ProductModel $leche;

    private ProductModel $arroz;

    /** A milk with 3 expired units out of 10, a rice without any, and the cashier. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->negocio = NegocioModel::factory()->create();
        $this->leche = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Leche entera 1 L', 'precio_venta' => '1000.00', 'stock' => 10]);
        $this->arroz = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Arroz 1 kg', 'precio_venta' => '1500.00', 'stock' => 10]);
        $cashier = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id]);
        Sanctum::actingAs($cashier, Role::Cajero->abilities());

        $this->scopeQueriesTo($this->negocio->id);
        DB::table('unidades_vencidas')->insert([
            'id' => (string) Str::uuid7(), 'negocio_id' => $this->negocio->id, 'producto_id' => $this->leche->id,
            'cantidad' => 3, 'motivo' => 'Vencida', 'estado' => 'activa', 'usuario_id' => $cashier->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** The sale goes on and the response warns about the product with expired units. */
    public function test_sale_warns_about_expired_units_without_blocking(): void
    {
        $this->postJson(self::ENDPOINT, ['lineas' => [
            ['producto_id' => $this->leche->id, 'cantidad' => 2],
            ['producto_id' => $this->arroz->id, 'cantidad' => 1],
        ]])
            ->assertCreated()
            ->assertJsonCount(1, 'data.advertencias')
            ->assertJsonPath('data.advertencias.0.tipo', 'unidades_vencidas')
            ->assertJsonPath('data.advertencias.0.producto_id', $this->leche->id)
            ->assertJsonPath('data.advertencias.0.unidades_vencidas', 3)
            ->assertJsonPath('data.advertencias.0.mensaje', 'Hay 3 unidades vencidas de Leche entera 1 L en el estante: no se pueden vender, pero las demás sí.');
    }

    /** A sale of products without expired units has no warnings. */
    public function test_no_warning_without_expired_units(): void
    {
        $this->postJson(self::ENDPOINT, ['lineas' => [['producto_id' => $this->arroz->id, 'cantidad' => 1]]])
            ->assertCreated()
            ->assertJsonPath('data.advertencias', []);
    }

    /** The expired units cannot be sold: only the 7 that are left are sellable. */
    public function test_expired_units_are_not_sellable(): void
    {
        $this->postJson(self::ENDPOINT, ['lineas' => [['producto_id' => $this->leche->id, 'cantidad' => 8]]])
            ->assertConflict()
            ->assertJsonPath('detail', 'No alcanza el stock de Leche entera 1 L. Disponible para vender: 7.');
    }

    /** Once the sale is paid there is nothing left to warn about. */
    public function test_paid_sale_has_no_warnings(): void
    {
        $id = $this->postJson(self::ENDPOINT, ['lineas' => [['producto_id' => $this->leche->id, 'cantidad' => 1]]])->json('data.id');

        $this->postJson(self::ENDPOINT.'/'.$id.'/cobrar')->assertOk()->assertJsonPath('data.advertencias', []);
    }
}
