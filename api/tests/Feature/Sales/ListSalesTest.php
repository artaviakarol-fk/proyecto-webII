<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\Factories\SaleFactory;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class ListSalesTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private const string ENDPOINT = '/api/v1/ventas';

    private NegocioModel $negocio;

    private UserModel $cajero;

    private ProductModel $arroz;

    /** A negocio with its cashier and one product, on a fixed day. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-15 09:30:00');
        $this->negocio = NegocioModel::factory()->create();
        $this->cajero = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Ana Caja']);
        $this->arroz = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'precio_venta' => '1500.00', 'stock' => 100]);
        Sanctum::actingAs($this->cajero, Role::Cajero->abilities());
    }

    /** The listing shows the sales newest first, without their lines, with the page described in meta. */
    public function test_lists_the_sales_newest_first_by_pages(): void
    {
        $sales = $this->sell();
        $this->travel(1)->minutes();
        $newest = SaleFactory::new()->for($this->negocio, $this->cajero)->line($this->arroz, 3)->paid()->create();

        $this->getJson(self::ENDPOINT.'?por_pagina=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newest)
            ->assertJsonPath('data.0.estado', 'cobrada')
            ->assertJsonPath('data.0.cantidad_lineas', 1)
            ->assertJsonPath('data.0.total', 4500)
            ->assertJsonPath('data.0.cajero', ['id' => $this->cajero->id, 'nombre' => 'Ana Caja'])
            ->assertJsonPath('data.0.creada_en', '2026-10-15T09:31:00-06:00')
            ->assertJsonMissingPath('data.0.lineas')
            ->assertJsonPath('meta', ['pagina' => 1, 'por_pagina' => 2, 'total' => 4, 'ultima_pagina' => 2]);

        $this->getJson(self::ENDPOINT.'?por_pagina=2&pagina=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagina', 2);

        $this->assertCount(3, $sales);
    }

    /** The listing filters by status and by the day the sale was opened, in Costa Rica time. */
    public function test_filters_by_status_and_day(): void
    {
        $this->sell();
        $this->travelTo('2026-10-16 23:30:00');
        $nextDay = SaleFactory::new()->for($this->negocio, $this->cajero)->line($this->arroz, 1)->create();

        $this->getJson(self::ENDPOINT.'?estado=anulada')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.estado', 'anulada');

        $this->getJson(self::ENDPOINT.'?fecha=2026-10-16')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $nextDay);

        $this->getJson(self::ENDPOINT.'?fecha=2026-10-15&estado=abierta')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /** A page past the end is empty, and invalid filters answer 422 in the vocabulary of the API. */
    public function test_empty_page_and_invalid_filters(): void
    {
        $this->sell();

        $this->getJson(self::ENDPOINT.'?pagina=9')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 3);

        $this->getJson(self::ENDPOINT.'?estado=perdida&fecha=15-10-2026&por_pagina=51')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['estado', 'fecha', 'por_pagina'], 'errores');
    }

    /** A negocio only lists its own sales. */
    public function test_lists_only_the_sales_of_the_negocio(): void
    {
        $this->sell();

        $other = NegocioModel::factory()->create();
        $otherCashier = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $other->id]);
        $otherProduct = ProductModel::factory()->create(['negocio_id' => $other->id, 'stock' => 10]);
        SaleFactory::new()->for($other, $otherCashier)->line($otherProduct, 1)->create();

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        Sanctum::actingAs($otherCashier, Role::Cajero->abilities());

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    /** The warehouse does not sell, so it cannot list the sales either. */
    public function test_warehouse_cannot_list_the_sales(): void
    {
        $bodeguero = UserModel::factory()->role(Role::Bodeguero)->create(['negocio_id' => $this->negocio->id]);
        Sanctum::actingAs($bodeguero, Role::Bodeguero->abilities());

        $this->getJson(self::ENDPOINT)->assertForbidden();
    }

    /**
     * Three sales of the negocio: one open, one paid and one cancelled.
     *
     * @return list<string>
     */
    private function sell(): array
    {
        $sale = SaleFactory::new()->for($this->negocio, $this->cajero)->line($this->arroz, 1);

        return [$sale->create(), $sale->paid()->create(), $sale->cancelled()->create()];
    }
}
