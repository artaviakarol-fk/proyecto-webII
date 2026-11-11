<?php

declare(strict_types=1);

namespace Tests\Feature\Seguridad;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\Factories\SaleFactory;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

/**
 * Each role reaches only what it is meant to reach, and no account reaches what belongs to another negocio.
 *
 * The matrix asks every route of the API with every role. A route a role may not use answers 403 before it
 * looks at the request, so the requests carry no real data: a role that is allowed gets past the check and
 * answers whatever the route answers to an empty request (404, 422...), never 401, 403 or a server error.
 */
final class PermisosRolTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private const string PROHIBIDO = 'http://localhost/problemas/prohibido';

    private const string NO_AUTENTICADO = 'http://localhost/problemas/no-autenticado';

    private const string NO_ENCONTRADO = 'http://localhost/problemas/no-encontrado';

    /** The payment routes build the gateway of the provider, so the test gives it a test key; nothing here calls a provider. */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.default' => 'onvo',
            'services.onvo.secret' => 'onvo_test_secret_key_permisos',
            'services.onvo.base_url' => 'https://api.onvopay.com/v1',
            'services.onvo.webhook_secret' => 'whsec_test',
        ]);
        Http::preventStrayRequests();
    }

    /** Every role of the staff against every route: who passes and who is refused. */
    #[DataProvider('roleAgainstRoute')]
    public function test_each_role_reaches_only_its_routes(Role $role, string $method, string $uri, bool $allowed): void
    {
        Sanctum::actingAs(UserModel::factory()->role($role)->create(), $role->abilities());

        $response = $this->json($method, '/api/v1'.$this->withIdentifier($uri));

        if ($allowed) {
            $this->assertNotContains($response->getStatusCode(), [401, 403], "{$role->value} should pass {$method} {$uri}");
            $this->assertLessThan(500, $response->getStatusCode(), "{$method} {$uri} failed for {$role->value}");

            return;
        }

        $response->assertForbidden()->assertJsonPath('type', self::PROHIBIDO);
    }

    /** Without a token nobody gets into any route of the staff. */
    #[DataProvider('routes')]
    public function test_without_a_token_every_route_answers_unauthorized(string $method, string $uri): void
    {
        $this->json($method, '/api/v1'.$this->withIdentifier($uri))
            ->assertUnauthorized()
            ->assertJsonPath('type', self::NO_AUTENTICADO);
    }

    /** What the token carries decides, not only the role of the account: a token reduced to the till cannot change prices. */
    public function test_the_abilities_of_the_token_limit_the_account(): void
    {
        $administrator = UserModel::factory()->role(Role::Administrador)->create();
        Sanctum::actingAs($administrator, ['caja']);

        $this->patchJson('/api/v1/productos/'.Str::uuid()->toString().'/precio', ['precio_venta' => 1])
            ->assertForbidden()
            ->assertJsonPath('type', self::PROHIBIDO);
        $this->postJson('/api/v1/ventas/'.Str::uuid()->toString().'/pago/reembolso')->assertForbidden();
    }

    /** A cashier cannot reach the sales of another negocio, however it asks for them, and nothing changes. */
    public function test_a_cashier_cannot_reach_the_sales_of_another_negocio(): void
    {
        [$negocio, $other, $otherProduct, $otherSale] = $this->twoNegocios();
        $cashier = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $negocio->id]);
        Sanctum::actingAs($cashier, Role::Cajero->abilities());

        foreach ([['GET', ''], ['POST', '/cobrar'], ['POST', '/anular'], ['GET', '/pago']] as [$method, $suffix]) {
            $this->json($method, "/api/v1/ventas/{$otherSale}{$suffix}")
                ->assertNotFound()
                ->assertJsonPath('type', self::NO_ENCONTRADO);
        }

        // The negocio is the one of the account: asking for another one in the request changes nothing.
        $this->getJson('/api/v1/ventas?negocio_id='.$other->id)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/negocio?negocio_id='.$other->id)->assertOk()->assertJsonPath('data.nombre', $negocio->nombre);

        $this->postJson('/api/v1/ventas', [
            'negocio_id' => $other->id,
            'lineas' => [['producto_id' => $otherProduct->id, 'cantidad' => 1]],
        ])->assertStatus(422);
    }

    /** A warehouse keeper passes the ability check on the products and still cannot reach those of another negocio. */
    public function test_a_warehouse_keeper_cannot_reach_the_products_of_another_negocio(): void
    {
        [$negocio, , $otherProduct] = $this->twoNegocios();
        $keeper = UserModel::factory()->role(Role::Bodeguero)->create(['negocio_id' => $negocio->id]);
        Sanctum::actingAs($keeper, Role::Bodeguero->abilities());

        $this->getJson("/api/v1/productos/{$otherProduct->id}")
            ->assertNotFound()
            ->assertJsonPath('type', self::NO_ENCONTRADO);
        $this->postJson("/api/v1/productos/{$otherProduct->id}/vencidos", ['cantidad' => 1, 'motivo' => 'Vencida'])
            ->assertNotFound()
            ->assertJsonPath('type', self::NO_ENCONTRADO);
    }

    /**
     * Every role against every route.
     *
     * @return array<string, array{Role, string, string, bool}>
     */
    public static function roleAgainstRoute(): array
    {
        $cases = [];

        foreach (self::matrix() as [$method, $uri, $allowedRoles]) {
            foreach (Role::cases() as $role) {
                $cases["{$role->value} {$method} {$uri}"] = [$role, $method, $uri, in_array($role, $allowedRoles, true)];
            }
        }

        return $cases;
    }

    /**
     * Every route of the staff.
     *
     * @return array<string, array{string, string}>
     */
    public static function routes(): array
    {
        $cases = [];

        foreach (self::matrix() as [$method, $uri]) {
            $cases["{$method} {$uri}"] = [$method, $uri];
        }

        return $cases;
    }

    /**
     * The routes of the staff and the roles that may use each one.
     *
     * @return list<array{string, string, list<Role>}>
     */
    private static function matrix(): array
    {
        $everyone = [Role::Administrador, Role::Cajero, Role::Bodeguero];
        $administration = [Role::Administrador];
        $till = [Role::Administrador, Role::Cajero];
        $warehouse = [Role::Administrador, Role::Bodeguero];

        return [
            // Catalog: everybody reads it, only the administration changes it.
            ['GET', '/productos', $everyone],
            ['GET', '/productos/{id}', $everyone],
            ['GET', '/productos/{id}/disponibilidad', $everyone],
            ['POST', '/productos', $administration],
            ['PUT', '/productos/{id}', $administration],
            ['DELETE', '/productos/{id}', $administration],
            ['PATCH', '/productos/{id}/precio', $administration],
            ['GET', '/productos/{id}/historial-precios', $administration],
            // Stock: the warehouse and the administration.
            ['POST', '/productos/{id}/vencidos', $warehouse],
            ['POST', '/entradas', $warehouse],
            ['GET', '/entradas/{id}', $warehouse],
            // Till: the cashier and the administration; only the administration refunds.
            ['GET', '/ventas', $till],
            ['POST', '/ventas', $till],
            ['GET', '/ventas/{id}', $till],
            ['PUT', '/ventas/{id}', $till],
            ['POST', '/ventas/{id}/cobrar', $till],
            ['POST', '/ventas/{id}/anular', $till],
            ['POST', '/ventas/{id}/pago', $till],
            ['GET', '/ventas/{id}/pago', $till],
            ['POST', '/ventas/{id}/pago/reembolso', $administration],
            // Reports: only the administration sees the income of the day.
            ['GET', '/reportes/cierre-diario', $administration],
            // Fiado: the till reads the customers and what they owe; only the administration changes them.
            ['GET', '/clientes', $till],
            ['GET', '/clientes/{id}', $till],
            ['POST', '/clientes', $administration],
            ['PUT', '/clientes/{id}', $administration],
            ['DELETE', '/clientes/{id}', $administration],
            ['POST', '/ventas/{id}/fiado', $till],
            ['POST', '/clientes/{id}/ampliaciones', $till],
            ['POST', '/clientes/{id}/abonos', $till],
            ['GET', '/clientes/{id}/abonos', $till],
            ['GET', '/abonos/{id}', $till],
            ['GET', '/abonos/{id}/recibo', $till],
            ['GET', '/ampliaciones-limite', $administration],
            ['POST', '/ampliaciones-limite/{id}/revertir', $administration],
            // Negocio: everybody sees it, only the administration changes it.
            ['GET', '/negocio', $everyone],
            ['GET', '/negocio/horario', $everyone],
            ['PUT', '/negocio/horario', $administration],
            ['POST', '/negocio/logo', $administration],
        ];
    }

    /** Give the route an identifier that exists nowhere: the check of the role comes before any lookup. */
    private function withIdentifier(string $uri): string
    {
        return str_replace('{id}', Str::uuid()->toString(), $uri);
    }

    /**
     * Two negocios; the second one has a product and an open sale.
     *
     * @return array{NegocioModel, NegocioModel, ProductModel, string}
     */
    private function twoNegocios(): array
    {
        $negocio = NegocioModel::factory()->create(['nombre' => 'Minisúper A']);
        $other = NegocioModel::factory()->create(['nombre' => 'Minisúper B']);
        $otherCashier = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $other->id]);
        $otherProduct = ProductModel::factory()->create(['negocio_id' => $other->id, 'nombre' => 'Arroz de B', 'stock' => 10]);
        $otherSale = SaleFactory::new()->for($other, $otherCashier)->line($otherProduct, 2)->create();

        return [$negocio, $other, $otherProduct, $otherSale];
    }
}
