<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory\Expirations;

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToNegocio;
use Tests\TestCase;

final class MarkExpiredUnitsTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private NegocioModel $negocio;

    private UserModel $bodeguero;

    private ProductModel $leche;

    /** A product with 10 units and the warehouse worker of its negocio. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->negocio = NegocioModel::factory()->create();
        $this->leche = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Leche entera 1 L', 'stock' => 10]);
        $this->bodeguero = UserModel::factory()->role(Role::Bodeguero)->create(['negocio_id' => $this->negocio->id]);
        Sanctum::actingAs($this->bodeguero, Role::Bodeguero->abilities());
    }

    /** Marking units stores them on the shelf, with who marked them, and shows what is left to sell. */
    public function test_warehouse_marks_units_as_expired(): void
    {
        $this->postJson($this->url(), ['cantidad' => 3, 'motivo' => 'Fecha de vencimiento cumplida'])
            ->assertCreated()
            ->assertJsonPath('data.producto_id', $this->leche->id)
            ->assertJsonPath('data.cantidad', 3)
            ->assertJsonPath('data.motivo', 'Fecha de vencimiento cumplida')
            ->assertJsonPath('data.estado', 'activa')
            ->assertJsonPath('data.registrada_por', $this->bodeguero->id)
            ->assertJsonPath('data.stock', 10)
            ->assertJsonPath('data.unidades_vencidas', 3)
            ->assertJsonPath('data.stock_vendible', 7);

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseHas('unidades_vencidas', ['producto_id' => $this->leche->id, 'cantidad' => 3, 'estado' => 'activa', 'usuario_id' => $this->bodeguero->id]);
    }

    /** The availability of the product reflects the marked units, and the stock itself does not change. */
    public function test_availability_reflects_the_marked_units(): void
    {
        $this->postJson($this->url(), ['cantidad' => 4, 'motivo' => 'Empaque inflado'])->assertCreated();
        $this->postJson($this->url(), ['cantidad' => 2, 'motivo' => 'Vencida hoy'])->assertCreated();

        $this->getJson('/api/v1/productos/'.$this->leche->id.'/disponibilidad')
            ->assertOk()
            ->assertJsonPath('data.stock', 10)
            ->assertJsonPath('data.unidades_vencidas', 6)
            ->assertJsonPath('data.stock_vendible', 4);
    }

    /** A product that was never received with an expiry date can still have expired units marked. */
    public function test_product_without_expiry_date_can_be_marked(): void
    {
        $this->postJson($this->url(), ['cantidad' => 1, 'motivo' => 'Se encontró vencida'])->assertCreated();
    }

    /** More units than the product has left without a mark are refused, and nothing is stored. */
    public function test_cannot_mark_more_units_than_the_product_has_unmarked(): void
    {
        $this->postJson($this->url(), ['cantidad' => 8, 'motivo' => 'Lote vencido'])->assertCreated();

        $this->postJson($this->url(), ['cantidad' => 3, 'motivo' => 'Otro lote'])
            ->assertConflict()
            ->assertJsonPath('detail', 'No se pueden marcar 3 unidades como vencidas: el producto solo tiene 2 unidades sin marcar.');

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame(1, DB::table('unidades_vencidas')->count());
    }

    /** The quantity and the reason are required and have limits. */
    public function test_quantity_and_reason_are_validated(): void
    {
        $this->postJson($this->url(), [])->assertUnprocessable()->assertJsonValidationErrors(['cantidad', 'motivo'], 'errores');
        $this->postJson($this->url(), ['cantidad' => 0, 'motivo' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('cantidad', 'errores');
        $this->postJson($this->url(), ['cantidad' => 1.5, 'motivo' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('cantidad', 'errores');
        $this->postJson($this->url(), ['cantidad' => 1, 'motivo' => str_repeat('a', 201)])->assertUnprocessable()->assertJsonValidationErrors('motivo', 'errores');
    }

    /** A product of another negocio is reported as missing. */
    public function test_product_of_another_negocio_is_not_found(): void
    {
        $other = ProductModel::factory()->create(['negocio_id' => NegocioModel::factory()->create()->id, 'stock' => 5]);

        $this->postJson('/api/v1/productos/'.$other->id.'/vencidos', ['cantidad' => 1, 'motivo' => 'x'])->assertNotFound();
    }

    /** The administration can mark too; the cashier cannot, and an anonymous caller is not authenticated. */
    public function test_only_warehouse_and_administration_can_mark(): void
    {
        Sanctum::actingAs(UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id]), Role::Cajero->abilities());
        $this->postJson($this->url(), ['cantidad' => 1, 'motivo' => 'x'])->assertForbidden();

        Sanctum::actingAs(UserModel::factory()->create(['negocio_id' => $this->negocio->id]), Role::Administrador->abilities());
        $this->postJson($this->url(), ['cantidad' => 1, 'motivo' => 'x'])->assertCreated();
    }

    /** Without a token the request is refused. */
    public function test_anonymous_caller_is_refused(): void
    {
        $this->app['auth']->forgetGuards();

        $this->postJson($this->url(), ['cantidad' => 1, 'motivo' => 'x'])->assertUnauthorized();
    }

    /** URL to mark expired units of the product. */
    private function url(): string
    {
        return '/api/v1/productos/'.$this->leche->id.'/vencidos';
    }
}
