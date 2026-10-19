<?php

declare(strict_types=1);

namespace Tests\Feature\Ventas;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToNegocio;
use Tests\TestCase;

/**
 * El licor se vende solo con la cédula del comprador. La cédula la revisa a mano el cajero: nunca se
 * valida contra un servicio externo. Todas las cédulas de estas pruebas son ficticias.
 */
final class VentaLicorTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private const string ENDPOINT = '/api/v1/ventas';

    private const string MISSING_ID_MESSAGE = 'La venta incluye licor: registre la cédula del comprador en cedula_comprador para completarla.';

    private const string FICTITIOUS_ID = '1-1111-1111';

    private NegocioModel $negocio;

    private ProductModel $arroz;

    private ProductModel $cerveza;

    /** A product without liquor, a beer, and the cashier of their negocio. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->negocio = NegocioModel::factory()->create();
        $this->arroz = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Arroz 1 kg', 'precio_venta' => '1500.00', 'stock' => 10]);
        $this->cerveza = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Cerveza Imperial', 'precio_venta' => '1000.00', 'stock' => 10, 'es_licor' => true]);
        Sanctum::actingAs(UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id]), Role::Cajero->abilities());
    }

    /** Without the id number a sale with liquor is refused with the reason, and nothing is sold. */
    public function test_sale_with_liquor_without_id_number_is_refused(): void
    {
        $this->sell([[$this->cerveza, 1]])
            ->assertUnprocessable()
            ->assertJsonPath('detail', self::MISSING_ID_MESSAGE);

        $this->sell([[$this->cerveza, 1]], '   ')
            ->assertUnprocessable()
            ->assertJsonPath('detail', self::MISSING_ID_MESSAGE);

        $this->assertSame(10, $this->stockOf($this->cerveza));
        $this->assertSame(0, DB::table('ventas')->count());
    }

    /** One liquor line is enough: a mixed sale also needs the id number, and its other lines are not sold either. */
    public function test_mixed_sale_with_liquor_needs_the_id_number(): void
    {
        $this->sell([[$this->arroz, 2], [$this->cerveza, 1]])
            ->assertUnprocessable()
            ->assertJsonPath('detail', self::MISSING_ID_MESSAGE);

        $this->assertSame(10, $this->stockOf($this->arroz));
    }

    /** With the id number the sale is made and the number is registered, written with digits only. */
    public function test_id_number_is_registered_with_the_sale(): void
    {
        $id = $this->sell([[$this->cerveza, 2]], self::FICTITIOUS_ID)
            ->assertCreated()
            ->assertJsonPath('data.cedula_comprador', '111111111')
            ->json('data.id');

        $this->getJson(self::ENDPOINT.'/'.$id)->assertOk()->assertJsonPath('data.cedula_comprador', '111111111');
        $this->assertSame(8, $this->stockOf($this->cerveza));
        $this->assertDatabaseHas('ventas', ['id' => $id, 'cedula_comprador' => '111111111']);
    }

    /** A sale without liquor does not need the id number. */
    public function test_sale_without_liquor_does_not_need_the_id_number(): void
    {
        $this->sell([[$this->arroz, 1]])
            ->assertCreated()
            ->assertJsonPath('data.cedula_comprador', null);
    }

    /** The number is not checked against any outside service, but it must look like an id number. */
    public function test_id_number_must_have_the_right_shape(): void
    {
        $this->sell([[$this->arroz, 1]], '12345')
            ->assertUnprocessable()
            ->assertJsonPath('detail', 'La cédula del comprador debe tener entre 9 y 12 dígitos.');

        $this->sell([[$this->arroz, 1]], '1-ABCD-1111')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cedula_comprador', 'errores');

        $this->assertSame(10, $this->stockOf($this->arroz));
    }

    /** Adding liquor to an open sale needs the id number too; the sale stays as it was. */
    public function test_revising_a_sale_to_add_liquor_needs_the_id_number(): void
    {
        $id = $this->sell([[$this->arroz, 1]])->json('data.id');
        $lines = ['lineas' => [
            ['producto_id' => $this->arroz->id, 'cantidad' => 1],
            ['producto_id' => $this->cerveza->id, 'cantidad' => 1],
        ]];

        $this->putJson(self::ENDPOINT.'/'.$id, $lines)
            ->assertUnprocessable()
            ->assertJsonPath('detail', self::MISSING_ID_MESSAGE);
        $this->assertSame(10, $this->stockOf($this->cerveza));

        $this->putJson(self::ENDPOINT.'/'.$id, [...$lines, 'cedula_comprador' => self::FICTITIOUS_ID])
            ->assertOk()
            ->assertJsonPath('data.cedula_comprador', '111111111');
        $this->assertSame(9, $this->stockOf($this->cerveza));
    }

    /** Revising a sale keeps the id number that was registered when the new body does not bring one. */
    public function test_revising_keeps_the_registered_id_number(): void
    {
        $id = $this->sell([[$this->cerveza, 1]], self::FICTITIOUS_ID)->json('data.id');

        $this->putJson(self::ENDPOINT.'/'.$id, ['lineas' => [['producto_id' => $this->cerveza->id, 'cantidad' => 3]]])
            ->assertOk()
            ->assertJsonPath('data.cedula_comprador', '111111111')
            ->assertJsonPath('data.total', 3000);
    }

    /**
     * Sell products, each with its units, and the id number of the buyer when there is one.
     *
     * @param  list<array{ProductModel, int}>  $items
     */
    private function sell(array $items, ?string $idNumber = null): TestResponse
    {
        return $this->postJson(self::ENDPOINT, [
            'lineas' => array_map(fn (array $item): array => ['producto_id' => $item[0]->id, 'cantidad' => $item[1]], $items),
            ...($idNumber === null ? [] : ['cedula_comprador' => $idNumber]),
        ]);
    }

    /** The units a product has now, read in the scope of its negocio. */
    private function stockOf(ProductModel $product): int
    {
        $this->scopeQueriesTo($product->negocio_id);

        return (int) DB::table('productos')->where('id', $product->id)->value('stock');
    }
}
