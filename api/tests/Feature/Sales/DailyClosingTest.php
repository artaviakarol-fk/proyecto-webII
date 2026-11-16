<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\Factories\SaleFactory;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

/** The income of the day, by way of paying, that only the administration sees. */
final class DailyClosingTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private const string ENDPOINT = '/api/v1/reportes/cierre-diario';

    private NegocioModel $negocio;

    private UserModel $cajero;

    private ProductModel $arroz;

    /** A negocio with its cashier and a product of 1500 colones, on a fixed day, seen by its administrator. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-10 20:00:00');
        $this->negocio = NegocioModel::factory()->create();
        $this->cajero = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id]);
        $this->arroz = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'precio_venta' => '1500.00', 'stock' => 100]);
        Sanctum::actingAs(UserModel::factory()->role(Role::Administrador)->create(['negocio_id' => $this->negocio->id]), Role::Administrador->abilities());
    }

    /** The closing adds the paid sales of the day by way of paying and counts them; fiado is there, in zero, until it is sold. */
    public function test_it_adds_the_paid_sales_of_the_day_by_way_of_paying(): void
    {
        $this->sell('efectivo', 2);
        $this->sell('efectivo', 1);
        $this->sell('tarjeta', 1);
        $this->sell('sinpe_movil', 3);

        $this->getJson(self::ENDPOINT.'?fecha=2026-11-10')
            ->assertOk()
            ->assertJsonPath('data.fecha', '2026-11-10')
            ->assertJsonPath('data.cantidad_ventas', 4)
            ->assertJsonPath('data.totales.efectivo', 4500)
            ->assertJsonPath('data.totales.tarjeta', 1500)
            ->assertJsonPath('data.totales.sinpe_movil', 4500)
            ->assertJsonPath('data.totales.fiado', 0)
            ->assertJsonPath('data.total', 10500);
    }

    /** Without a date the closing is the one of today. */
    public function test_without_a_date_it_is_today(): void
    {
        $this->sell('efectivo', 2);

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.fecha', '2026-11-10')
            ->assertJsonPath('data.cantidad_ventas', 1)
            ->assertJsonPath('data.total', 3000);
    }

    /** Another day is asked with the date, and only its sales count. */
    public function test_it_can_be_asked_for_another_day(): void
    {
        $this->sell('efectivo', 2);
        $this->sell('tarjeta', 1, '2026-11-09 15:00:00');

        $this->getJson(self::ENDPOINT.'?fecha=2026-11-09')
            ->assertOk()
            ->assertJsonPath('data.cantidad_ventas', 1)
            ->assertJsonPath('data.totales.efectivo', 0)
            ->assertJsonPath('data.totales.tarjeta', 1500)
            ->assertJsonPath('data.total', 1500);
    }

    /** The day of the negocio goes from midnight to midnight, Costa Rica time. */
    public function test_the_day_goes_from_midnight_to_midnight_of_the_negocio(): void
    {
        $this->sell('efectivo', 1, '2026-11-09 23:59:59');
        $this->sell('efectivo', 1, '2026-11-10 00:00:00');
        $this->sell('efectivo', 1, '2026-11-10 23:59:59');
        $this->sell('efectivo', 1, '2026-11-11 00:00:00');

        $this->getJson(self::ENDPOINT.'?fecha=2026-11-10')
            ->assertOk()
            ->assertJsonPath('data.cantidad_ventas', 2)
            ->assertJsonPath('data.total', 3000);
    }

    /** Open and cancelled sales, and the sales of another negocio, do not count. */
    public function test_only_the_paid_sales_of_the_own_negocio_count(): void
    {
        $this->sell('efectivo', 1);
        SaleFactory::new()->for($this->negocio, $this->cajero)->line($this->arroz, 5)->create();
        SaleFactory::new()->for($this->negocio, $this->cajero)->line($this->arroz, 4)->cancelled()->create();

        $other = NegocioModel::factory()->create();
        $otherCashier = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $other->id]);
        $otherProduct = ProductModel::factory()->create(['negocio_id' => $other->id, 'precio_venta' => '9000.00', 'stock' => 10]);
        SaleFactory::new()->for($other, $otherCashier)->line($otherProduct, 1)->paidWith('efectivo')->create();

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.cantidad_ventas', 1)
            ->assertJsonPath('data.total', 1500);
    }

    /** A day with no sales answers zeros, and the closing has no cash count, missing or extra money. */
    public function test_a_day_without_sales_is_zero_and_has_no_cash_count(): void
    {
        $response = $this->getJson(self::ENDPOINT.'?fecha=2026-10-01')
            ->assertOk()
            ->assertJsonPath('data.cantidad_ventas', 0)
            ->assertJsonPath('data.total', 0);

        $this->assertSame(['fecha', 'cantidad_ventas', 'totales', 'total'], array_keys($response->json('data')));
        $this->assertSame(['efectivo', 'tarjeta', 'sinpe_movil', 'fiado'], array_keys($response->json('data.totales')));
    }

    /** Only the administration sees it: the cashier and the warehouse keeper are refused, and so is whoever has no token. */
    public function test_only_the_administration_sees_the_closing(): void
    {
        foreach ([Role::Cajero, Role::Bodeguero] as $role) {
            Sanctum::actingAs(UserModel::factory()->role($role)->create(['negocio_id' => $this->negocio->id]), $role->abilities());

            $this->getJson(self::ENDPOINT)
                ->assertForbidden()
                ->assertJsonPath('type', 'http://localhost/problemas/prohibido');
        }

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        $this->getJson(self::ENDPOINT)->assertUnauthorized();
    }

    /** The date must be a real day written as year-month-day. */
    public function test_the_date_is_validated(): void
    {
        foreach (['10-11-2026', 'ayer', '2026-13-40'] as $date) {
            $this->getJson(self::ENDPOINT.'?fecha='.$date)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('fecha', 'errores');
        }
    }

    /** Store a paid sale of some kilos of rice, paid with a way of paying at a moment of the day (now by default). */
    private function sell(string $method, int $kilos, ?string $paidAt = null): void
    {
        SaleFactory::new()
            ->for($this->negocio, $this->cajero)
            ->line($this->arroz, $kilos)
            ->paidWith($method, $paidAt === null ? null : Carbon::parse($paidAt))
            ->create();
    }
}
