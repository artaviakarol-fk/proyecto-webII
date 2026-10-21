<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory\Products;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToNegocio;
use Tests\TestCase;

final class ManageProductsTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private const string ENDPOINT = '/api/v1/productos';

    private const string MISSING_ID = '019a0000-7b1e-7c3a-9f1d-2b6a4e8c0d99';

    private NegocioModel $negocio;

    private NegocioModel $otherNegocio;

    /** Create two negocios and act as the administrator of the first one. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->negocio = NegocioModel::factory()->create();
        $this->otherNegocio = NegocioModel::factory()->create();
        $this->actAs(Role::Administrador);
    }

    /** The administrator registers a product; it belongs to the negocio of the account, never to the one sent. */
    public function test_administrator_creates_a_product(): void
    {
        $response = $this->postJson(self::ENDPOINT, [...$this->payload(), 'negocio_id' => $this->otherNegocio->id]);

        $id = $response->json('data.id');
        $response
            ->assertCreated()
            ->assertHeader('Location', 'http://localhost'.self::ENDPOINT.'/'.$id)
            ->assertExactJson(['data' => [
                'id' => $id,
                'codigo' => '7441001100011',
                'nombre' => 'Arroz 1 kg',
                'categoria' => 'Abarrotes',
                'precio_venta' => 1350.5,
                'costo_actual' => 1050,
                'stock' => 40,
                'es_perecedero' => false,
                'es_licor' => false,
                'foto' => null,
                'agotado' => false,
            ]]);

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseHas('productos', ['id' => $id, 'negocio_id' => $this->negocio->id, 'precio_venta' => '1350.50']);
    }

    /** Only the code, the name and the sale price are required; the rest take their defaults. */
    public function test_optional_fields_take_their_defaults(): void
    {
        $this->postJson(self::ENDPOINT, ['codigo' => 'PAN-01', 'nombre' => 'Pan casero', 'precio_venta' => 800])
            ->assertCreated()
            ->assertJsonPath('data.categoria', null)
            ->assertJsonPath('data.costo_actual', 0)
            ->assertJsonPath('data.stock', 0)
            ->assertJsonPath('data.agotado', true);
    }

    /** The list shows one page of the products of the negocio ordered by name, with the page data. */
    public function test_listing_is_paginated_and_ordered_by_name(): void
    {
        foreach (['Café', 'Arroz', 'Leche'] as $name) {
            $this->product(['nombre' => $name]);
        }
        $this->product(['nombre' => 'Azúcar'], $this->otherNegocio);

        $this->getJson(self::ENDPOINT.'?por_pagina=2')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Arroz', 'Café'])
            ->assertJsonPath('meta', ['pagina' => 1, 'por_pagina' => 2, 'total' => 3, 'ultima_pagina' => 2]);

        $this->getJson(self::ENDPOINT.'?por_pagina=2&pagina=2')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Leche']);
    }

    /** The search matches part of the name, ignoring case, or part of the code. */
    #[DataProvider('searches')]
    public function test_search_by_name_or_code(string $term, array $expected): void
    {
        $this->product(['nombre' => 'Arroz blanco', 'codigo' => '7441001100011']);
        $this->product(['nombre' => 'Arroz integral', 'codigo' => '7441001100028']);
        $this->product(['nombre' => 'Leche entera', 'codigo' => 'LECHE-1L']);

        $this->getJson(self::ENDPOINT.'?buscar='.urlencode($term))
            ->assertOk()
            ->assertJsonPath('data.*.nombre', $expected);
    }

    /**
     * Search terms and the names they find.
     *
     * @return array<string, array{string, list<string>}>
     */
    public static function searches(): array
    {
        return [
            'parte del nombre' => ['arroz', ['Arroz blanco', 'Arroz integral']],
            'parte del código' => ['100028', ['Arroz integral']],
            'código completo' => ['LECHE-1L', ['Leche entera']],
            'comodín escapado' => ['%', []],
        ];
    }

    /** One product is shown by its identifier. */
    public function test_product_is_shown(): void
    {
        $product = $this->product(['nombre' => 'Café molido']);

        $this->getJson(self::ENDPOINT.'/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Café molido');
    }

    /** PUT replaces the data of the product. */
    public function test_administrator_replaces_a_product(): void
    {
        $product = $this->product(['codigo' => 'CAFE-1']);

        $this->putJson(self::ENDPOINT.'/'.$product->id, $this->payload(['codigo' => 'CAFE-1', 'nombre' => 'Café 500 g', 'precio_venta' => '2500']))
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Café 500 g')
            ->assertJsonPath('data.precio_venta', 2500);
    }

    /** Deleting is logical: the row stays with deleted_at, it leaves the list and its code can be used again. */
    public function test_delete_is_logical_and_frees_the_code(): void
    {
        $product = $this->product(['codigo' => 'LECHE-1L']);

        $this->deleteJson(self::ENDPOINT.'/'.$product->id)->assertNoContent();

        $this->getJson(self::ENDPOINT.'/'.$product->id)->assertNotFound();
        $this->getJson(self::ENDPOINT)->assertJsonPath('meta.total', 0);
        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSoftDeleted('productos', ['id' => $product->id]);

        $this->postJson(self::ENDPOINT, $this->payload(['codigo' => 'LECHE-1L']))->assertCreated();
    }

    /** A sale price of zero, negative or with more than two decimals is rejected in RFC 9457 format. */
    #[DataProvider('invalidPrices')]
    public function test_invalid_sale_price_is_rejected(mixed $price, string $message): void
    {
        $this->postJson(self::ENDPOINT, $this->payload(['precio_venta' => $price]))
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'http://localhost/problemas/datos-invalidos')
            ->assertJsonPath('errores.precio_venta.0', $message);
    }

    /**
     * Prices that are not accepted and the message each one produces.
     *
     * @return array<string, array{mixed, string}>
     */
    public static function invalidPrices(): array
    {
        return [
            'cero' => [0, 'El campo precio de venta debe ser mayor que 0.'],
            'negativo' => [-500, 'El campo precio de venta debe ser mayor que 0.'],
            'tres decimales' => ['10.555', 'El campo precio de venta debe tener 0-2 decimales.'],
            'sin precio' => [null, 'El campo precio de venta es obligatorio.'],
        ];
    }

    /** A code is unique inside a negocio, but another negocio may use the same one. */
    public function test_code_is_unique_per_negocio(): void
    {
        $this->product(['codigo' => '7441001100011']);
        $this->product(['codigo' => '7441001100042'], $this->otherNegocio);

        $this->postJson(self::ENDPOINT, $this->payload(['codigo' => '7441001100011']))
            ->assertUnprocessable()
            ->assertJsonPath('errores.codigo.0', 'El valor del campo código ya está en uso.');

        $this->postJson(self::ENDPOINT, $this->payload(['codigo' => '7441001100042']))->assertCreated();
    }

    /** The cashier and the warehouse keeper read the catalog but cannot change it. */
    #[DataProvider('staffWithoutAdministration')]
    public function test_staff_without_administration_only_reads(Role $role): void
    {
        $product = $this->product();
        $this->actAs($role);

        $this->getJson(self::ENDPOINT)->assertOk();
        $this->getJson(self::ENDPOINT.'/'.$product->id)->assertOk();

        foreach ([
            $this->postJson(self::ENDPOINT, $this->payload()),
            $this->putJson(self::ENDPOINT.'/'.$product->id, $this->payload()),
            $this->deleteJson(self::ENDPOINT.'/'.$product->id),
        ] as $response) {
            $response->assertForbidden()->assertJsonPath('type', 'http://localhost/problemas/prohibido');
        }
    }

    /**
     * Roles that only read the catalog.
     *
     * @return array<string, array{Role}>
     */
    public static function staffWithoutAdministration(): array
    {
        return ['cajero' => [Role::Cajero], 'bodeguero' => [Role::Bodeguero]];
    }

    /** A product of another negocio answers the same 404 as a missing one, and stays as it was. */
    #[DataProvider('accessesById')]
    public function test_product_of_another_negocio_answers_like_a_missing_one(string $method, bool $withPayload): void
    {
        $foreign = $this->product(['nombre' => 'Arroz de B'], $this->otherNegocio);
        $payload = $withPayload ? $this->payload() : [];

        $foreignResponse = $this->json($method, self::ENDPOINT.'/'.$foreign->id, $payload);
        $missingResponse = $this->json($method, self::ENDPOINT.'/'.self::MISSING_ID, $payload);

        $foreignResponse->assertNotFound()->assertJsonPath('type', 'http://localhost/problemas/no-encontrado');
        $this->assertSame(Arr::except($missingResponse->json(), 'instance'), Arr::except($foreignResponse->json(), 'instance'));

        $this->scopeQueriesTo($this->otherNegocio->id);
        $this->assertSame('Arroz de B', DB::table('productos')->where('id', $foreign->id)->whereNull('deleted_at')->value('nombre'));
    }

    /**
     * Requests that reach a product by its identifier.
     *
     * @return array<string, array{string, bool}>
     */
    public static function accessesById(): array
    {
        return [
            'leer' => ['GET', false],
            'reemplazar' => ['PUT', true],
            'borrar' => ['DELETE', false],
        ];
    }

    /** Without a token every route of the catalog answers 401. */
    public function test_guest_cannot_use_the_catalog(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson(self::ENDPOINT)
            ->assertUnauthorized()
            ->assertJsonPath('type', 'http://localhost/problemas/no-autenticado');
    }

    /** Authenticate an account of the first negocio with the abilities of a role. */
    private function actAs(Role $role): void
    {
        Sanctum::actingAs(
            UserModel::factory()->role($role)->create(['negocio_id' => $this->negocio->id]),
            $role->abilities(),
        );
    }

    /**
     * Create a product, by default of the first negocio.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function product(array $attributes = [], ?NegocioModel $negocio = null): ProductModel
    {
        return ProductModel::factory()->create(['negocio_id' => ($negocio ?? $this->negocio)->id, ...$attributes]);
    }

    /**
     * A valid product with some fields replaced.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'codigo' => '7441001100011',
            'nombre' => 'Arroz 1 kg',
            'categoria' => 'Abarrotes',
            'precio_venta' => 1350.5,
            'costo_actual' => 1050,
            'stock' => 40,
            'es_perecedero' => false,
            'es_licor' => false,
            ...$overrides,
        ];
    }
}
