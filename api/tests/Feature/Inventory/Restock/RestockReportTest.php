<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory\Restock;

use Carbon\CarbonInterface;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\Factories\SaleFactory;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class RestockReportTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private const string ENDPOINT = '/api/v1/inventario/reposicion';

    private NegocioModel $negocio;

    private UserModel $cashier;

    /** A negocio with a cashier and a warehouse worker logged in. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->negocio = NegocioModel::factory()->create();
        $this->cashier = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id]);
        $this->actAs(Role::Bodeguero);
    }

    /** The products come from the most urgent, with their pace and the units to order. */
    public function test_it_lists_the_products_from_the_most_urgent(): void
    {
        $ok = $this->product('Sal', 100);
        $low = $this->product('Arroz', 8);
        $critical = $this->product('Leche', 3);
        $out = $this->product('Atún', 0);
        $this->sell($ok, 3);
        $this->sell($low, 40);
        $this->sell($critical, 30);
        $this->sell($out, 6);

        $response = $this->getJson(self::ENDPOINT)->assertOk()->assertJsonPath('data.dias', 30);

        $this->assertSame(['Atún', 'Leche', 'Arroz', 'Sal'], $response->json('data.productos.*.nombre'));
        $this->assertSame(['agotado', 'critico', 'bajo', 'ok'], $response->json('data.productos.*.estado'));
        $response->assertJsonPath('data.productos.1.vendidas', 30)
            ->assertJsonPath('data.productos.1.promedio_diario', 1)
            ->assertJsonPath('data.productos.1.dias_restantes', 3)
            ->assertJsonPath('data.productos.1.sugerido', 11)
            ->assertJsonPath('data.productos.0.sugerido', 3)
            ->assertJsonPath('data.productos.3.sugerido', 0);
    }

    /** Only paid sales inside the window count: open, cancelled and old ones do not. */
    public function test_it_counts_only_paid_recent_sales(): void
    {
        $product = $this->product('Arroz', 10);
        $this->sell($product, 5);
        SaleFactory::new()->for($this->negocio, $this->cashier)->line($product, 7)->create();
        SaleFactory::new()->for($this->negocio, $this->cashier)->line($product, 9)->cancelled()->create();
        $this->sell($product, 11, now()->subDays(45));

        $this->getJson(self::ENDPOINT)->assertOk()->assertJsonPath('data.productos.0.vendidas', 5);
    }

    /** A product that did not sell is fine and has no days left. */
    public function test_a_product_without_sales_is_fine(): void
    {
        $this->product('Sal', 20);

        $this->getJson(self::ENDPOINT)->assertOk()
            ->assertJsonPath('data.productos.0.estado', 'ok')
            ->assertJsonPath('data.productos.0.dias_restantes', null)
            ->assertJsonPath('data.productos.0.sugerido', 0)
            ->assertJsonPath('data.mas_vendidos', []);
    }

    /** The best sellers are the five that sold most, the best first. */
    public function test_it_lists_the_five_best_sellers(): void
    {
        foreach ([1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E', 6 => 'F'] as $units => $name) {
            $this->sell($this->product($name, 50), $units);
        }

        $this->getJson(self::ENDPOINT)->assertOk()
            ->assertJsonCount(5, 'data.mas_vendidos')
            ->assertJsonPath('data.mas_vendidos.0.nombre', 'F')
            ->assertJsonPath('data.mas_vendidos.4.nombre', 'B');
    }

    /** The window changes what counts as recent. */
    public function test_the_days_change_the_window(): void
    {
        $product = $this->product('Arroz', 10);
        $this->sell($product, 4, now()->subDays(20));

        $this->getJson(self::ENDPOINT.'?dias=7')->assertOk()
            ->assertJsonPath('data.dias', 7)
            ->assertJsonPath('data.productos.0.vendidas', 0);
        $this->getJson(self::ENDPOINT.'?dias=30')->assertOk()->assertJsonPath('data.productos.0.vendidas', 4);
    }

    /** The window has to be between 7 and 90 days. */
    public function test_the_days_must_be_in_range(): void
    {
        $this->getJson(self::ENDPOINT.'?dias=3')->assertUnprocessable();
        $this->getJson(self::ENDPOINT.'?dias=91')->assertUnprocessable();
        $this->getJson(self::ENDPOINT.'?dias=abc')->assertUnprocessable();
    }

    /** Products and sales of another negocio never show up. */
    public function test_it_ignores_other_negocios(): void
    {
        $other = NegocioModel::factory()->create();
        $otherCashier = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $other->id]);
        $foreign = ProductModel::factory()->create(['negocio_id' => $other->id, 'nombre' => 'Ajeno', 'stock' => 1]);
        SaleFactory::new()->for($other, $otherCashier)->line($foreign, 20)->paidWith('efectivo', now()->subDay())->create();
        $mine = $this->product('Mío', 5);
        $this->sell($mine, 2);

        $response = $this->getJson(self::ENDPOINT)->assertOk();

        $this->assertSame(['Mío'], $response->json('data.productos.*.nombre'));
        $this->assertSame(['Mío'], $response->json('data.mas_vendidos.*.nombre'));
    }

    /** The cashier and the cliente cannot read it. */
    public function test_it_is_only_for_the_warehouse_roles(): void
    {
        $this->actAs(Role::Cajero);
        $this->getJson(self::ENDPOINT)->assertForbidden();

        $this->actAs(Role::Cliente);
        $this->getJson(self::ENDPOINT)->assertForbidden();
    }

    /** The administrator and the supervisor can read it too. */
    public function test_administration_and_supervisor_can_read_it(): void
    {
        foreach ([Role::Administrador, Role::Supervisor] as $role) {
            $this->actAs($role);
            $this->getJson(self::ENDPOINT)->assertOk();
        }
    }

    private function actAs(Role $role): void
    {
        Sanctum::actingAs(UserModel::factory()->role($role)->create(['negocio_id' => $this->negocio->id]), $role->abilities());
    }

    private function product(string $name, int $stock): ProductModel
    {
        return ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => $name, 'stock' => $stock]);
    }

    /** A paid sale of the product, one day ago unless told otherwise. */
    private function sell(ProductModel $product, int $units, ?CarbonInterface $at = null): void
    {
        SaleFactory::new()->for($this->negocio, $this->cashier)->line($product, $units)->paidWith('efectivo', $at ?? now()->subDay())->create();
    }
}
