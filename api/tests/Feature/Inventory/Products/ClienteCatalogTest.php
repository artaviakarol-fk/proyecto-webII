<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory\Products;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Src\Shared\Infrastructure\Storage\DiskImageStorage;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToNegocio;
use Tests\TestCase;

final class ClienteCatalogTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private const string ENDPOINT = '/api/v1/catalogo';

    private NegocioModel $negocio;

    private ProductModel $leche;

    /** A negocio with one product and a cliente of that negocio logged in. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->negocio = NegocioModel::factory()->create();
        $this->leche = ProductModel::factory()->create([
            'negocio_id' => $this->negocio->id,
            'codigo' => 'LECHE-1L',
            'nombre' => 'Leche entera 1 L',
            'categoria' => 'Lácteos',
            'precio_venta' => '950.00',
            'costo_actual' => '700.00',
            'stock' => 20,
        ]);
        Sanctum::actingAs(
            UserModel::factory()->role(Role::Cliente)->create(['negocio_id' => $this->negocio->id]),
            Role::Cliente->abilities(),
        );
    }

    /** The cliente sees the name, the price and the units available; the cost, the code and the stock are not sent. */
    public function test_cliente_sees_price_and_available_units_but_never_the_cost(): void
    {
        $response = $this->getJson(self::ENDPOINT)->assertOk();

        $response
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->leche->id)
            ->assertJsonPath('data.0.nombre', 'Leche entera 1 L')
            ->assertJsonPath('data.0.categoria', 'Lácteos')
            ->assertJsonPath('data.0.precio_venta', 950)
            ->assertJsonPath('data.0.disponible', 20)
            ->assertJsonPath('data.0.agotado', false)
            ->assertJsonPath('data.0.es_licor', false)
            ->assertJsonPath('data.0.foto', null)
            ->assertJsonPath('meta.total', 1);

        $this->assertSame(
            ['id', 'nombre', 'categoria', 'precio_venta', 'disponible', 'agotado', 'es_licor', 'foto'],
            array_keys($response->json('data.0')),
        );
        $this->assertStringNotContainsString('costo', $response->getContent());
    }

    /** Units marked as expired cannot be bought, so they leave the available units. */
    public function test_expired_units_are_not_available(): void
    {
        $this->expire(3);

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.0.disponible', 17)
            ->assertJsonPath('data.0.agotado', false);
    }

    /** A product without units to sell is still listed, marked as sold out. */
    public function test_product_without_units_is_listed_as_sold_out(): void
    {
        $this->expire(20);

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.0.disponible', 0)
            ->assertJsonPath('data.0.agotado', true);
    }

    /** The catalog is the one of the negocio of the cliente: products of another negocio never appear. */
    public function test_cliente_only_sees_the_products_of_its_negocio(): void
    {
        $other = NegocioModel::factory()->create();
        ProductModel::factory()->create(['negocio_id' => $other->id, 'nombre' => 'Arroz del otro negocio']);

        $this->getJson(self::ENDPOINT.'?negocio_id='.$other->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Leche entera 1 L');
    }

    /** The cliente searches by name and the listing comes by pages. */
    public function test_catalog_is_searched_and_paginated(): void
    {
        ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Pan cuadrado']);
        ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Arroz 1 kg']);

        $this->getJson(self::ENDPOINT.'?buscar=pan')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Pan cuadrado');

        $this->getJson(self::ENDPOINT.'?por_pagina=2&pagina=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.ultima_pagina', 2);
    }

    /** A product with photo links to the photo route of the catalog, which the cliente can download. */
    public function test_cliente_downloads_the_photo_through_the_catalog(): void
    {
        Storage::fake(DiskImageStorage::DISK);
        $path = 'negocios/'.$this->negocio->id.'/productos/'.$this->leche->id.'/leche.png';
        Storage::disk(DiskImageStorage::DISK)->put($path, 'imagen');
        $this->scopeQueriesTo($this->negocio->id);
        DB::table('productos')->where('id', $this->leche->id)->update(['foto_path' => $path]);

        $photoUrl = self::ENDPOINT.'/'.$this->leche->id.'/foto';

        $this->getJson(self::ENDPOINT)->assertOk()->assertJsonPath('data.0.foto', 'http://localhost'.$photoUrl);
        $this->get($photoUrl)->assertOk();
    }

    /** The catalog with costs of the staff stays closed to the cliente. */
    public function test_cliente_cannot_use_the_catalog_of_the_staff(): void
    {
        $this->getJson('/api/v1/productos')->assertForbidden();
        $this->getJson('/api/v1/productos/'.$this->leche->id)->assertForbidden();
        $this->getJson('/api/v1/productos/'.$this->leche->id.'/disponibilidad')->assertForbidden();
    }

    /** Without a token there is no catalog. */
    public function test_guest_cannot_see_the_catalog(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson(self::ENDPOINT)->assertUnauthorized();
    }

    /** Mark units of the product as expired and still on the shelf. */
    private function expire(int $units): void
    {
        $keeper = UserModel::factory()->role(Role::Bodeguero)->create(['negocio_id' => $this->negocio->id]);

        $this->scopeQueriesTo($this->negocio->id);
        DB::table('unidades_vencidas')->insert([
            'id' => (string) Str::uuid7(),
            'negocio_id' => $this->negocio->id,
            'producto_id' => $this->leche->id,
            'cantidad' => $units,
            'motivo' => 'Encontrada vencida en el estante',
            'estado' => 'activa',
            'usuario_id' => $keeper->id,
        ]);
    }
}
