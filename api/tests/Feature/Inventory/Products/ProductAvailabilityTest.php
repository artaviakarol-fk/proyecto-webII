<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory\Products;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToNegocio;
use Tests\TestCase;

final class ProductAvailabilityTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private NegocioModel $negocio;

    private UserModel $user;

    private ProductModel $leche;

    /** A product with 20 units and the cashier of its negocio. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->negocio = NegocioModel::factory()->create();
        $this->leche = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'codigo' => 'LECHE-1L', 'nombre' => 'Leche entera 1 L', 'stock' => 20]);
        $this->user = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id]);
        Sanctum::actingAs($this->user, Role::Cajero->abilities());
    }

    /** Without expired units the whole stock is sellable. */
    public function test_whole_stock_is_sellable_without_expired_units(): void
    {
        $this->getJson($this->url())
            ->assertOk()
            ->assertExactJson(['data' => [
                'producto_id' => $this->leche->id,
                'codigo' => 'LECHE-1L',
                'nombre' => 'Leche entera 1 L',
                'stock' => 20,
                'unidades_vencidas' => 0,
                'stock_vendible' => 20,
                'agotado' => false,
            ]]);
    }

    /** Expired units still on the shelf leave the sellable stock; retired ones no longer count. */
    public function test_expired_units_leave_the_sellable_stock(): void
    {
        $this->expire(2, 'activa');
        $this->expire(1, 'activa');
        $this->expire(5, 'retirada');

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.stock', 20)
            ->assertJsonPath('data.unidades_vencidas', 3)
            ->assertJsonPath('data.stock_vendible', 17)
            ->assertJsonPath('data.agotado', false);
    }

    /** A product is sold out with no stock, or when every unit is expired; the sellable stock is never negative. */
    #[DataProvider('soldOut')]
    public function test_product_is_reported_as_sold_out(int $stock, int $expired): void
    {
        $this->scopeQueriesTo($this->negocio->id);
        DB::table('productos')->where('id', $this->leche->id)->update(['stock' => $stock]);
        if ($expired > 0) {
            $this->expire($expired, 'activa');
        }

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.stock_vendible', 0)
            ->assertJsonPath('data.agotado', true);
    }

    /**
     * Stock and expired units that leave nothing to sell.
     *
     * @return array<string, array{int, int}>
     */
    public static function soldOut(): array
    {
        return [
            'sin stock' => [0, 0],
            'todo vencido' => [4, 4],
            'más vencidas que stock' => [2, 3],
        ];
    }

    /** A delivery increases what can be sold. */
    public function test_a_receipt_increases_the_sellable_stock(): void
    {
        Sanctum::actingAs(UserModel::factory()->role(Role::Bodeguero)->create(['negocio_id' => $this->negocio->id]), Role::Bodeguero->abilities());
        $this->postJson('/api/v1/entradas', [
            'proveedor' => 'Dos Pinos',
            'fecha' => now()->toDateString(),
            'lineas' => [['producto_id' => $this->leche->id, 'cantidad' => 12, 'costo_unitario' => 700]],
        ])->assertCreated();

        Sanctum::actingAs($this->user, Role::Cajero->abilities());
        $this->getJson($this->url())->assertJsonPath('data.stock_vendible', 32);
    }

    /** The availability of a product of another negocio answers 404, like a missing one. */
    public function test_product_of_another_negocio_answers_not_found(): void
    {
        $foreign = ProductModel::factory()->create(['negocio_id' => NegocioModel::factory()->create()->id]);

        $this->getJson("/api/v1/productos/{$foreign->id}/disponibilidad")
            ->assertNotFound()
            ->assertJsonPath('type', 'http://localhost/problemas/no-encontrado');
    }

    /** Every staff role checks availability; without a token it answers 401. */
    public function test_every_staff_role_checks_availability(): void
    {
        foreach (Role::cases() as $role) {
            Sanctum::actingAs(UserModel::factory()->role($role)->create(['negocio_id' => $this->negocio->id]), $role->abilities());
            $this->getJson($this->url())->assertOk();
        }

        $this->app['auth']->forgetGuards();
        $this->getJson($this->url())->assertUnauthorized();
    }

    /** Mark units of the product as expired, in a state. */
    private function expire(int $units, string $state): void
    {
        $this->scopeQueriesTo($this->negocio->id);
        DB::table('unidades_vencidas')->insert([
            'id' => (string) Str::uuid7(),
            'negocio_id' => $this->negocio->id,
            'producto_id' => $this->leche->id,
            'cantidad' => $units,
            'motivo' => 'Encontrada vencida en el estante',
            'estado' => $state,
            'usuario_id' => $this->user->id,
        ]);
    }

    /** Route of the availability of the product. */
    private function url(): string
    {
        return "/api/v1/productos/{$this->leche->id}/disponibilidad";
    }
}
