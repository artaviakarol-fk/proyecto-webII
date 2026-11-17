<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory\Reservations;

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

/**
 * Dos clientes quieren la misma mercadería. Una reserva aparta unidades por 15 minutos sin sacarlas del stock;
 * la base de datos serializa las reservas con SELECT ... FOR UPDATE sobre la fila del producto, así que el orden
 * de llegada decide quién se queda con la última unidad. Al vencer, la reserva deja de contar sola.
 */
final class ReservationsTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private const string ENDPOINT = '/api/v1/reservas';

    private const string CONFLICTO = 'http://localhost/problemas/conflicto';

    private const string NO_ENCONTRADO = 'http://localhost/problemas/no-encontrado';

    private NegocioModel $negocio;

    private UserModel $ana;

    private UserModel $beto;

    private ProductModel $leche;

    /** A product with 5 units and two clientes of the same negocio. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->negocio = NegocioModel::factory()->create();
        $this->leche = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Leche entera 1 L', 'precio_venta' => '950.00', 'stock' => 5]);
        $this->ana = UserModel::factory()->role(Role::Cliente)->create(['negocio_id' => $this->negocio->id]);
        $this->beto = UserModel::factory()->role(Role::Cliente)->create(['negocio_id' => $this->negocio->id]);
    }

    /** A cliente holds units of a product for 15 minutes and sees what they cost. */
    public function test_cliente_reserves_units_for_fifteen_minutes(): void
    {
        $now = $this->freezeSecond();
        $this->actAs($this->ana);

        $response = $this->postJson(self::ENDPOINT, $this->body(2))->assertCreated();

        $response
            ->assertJsonPath('data.producto_id', $this->leche->id)
            ->assertJsonPath('data.nombre', 'Leche entera 1 L')
            ->assertJsonPath('data.cantidad', 2)
            ->assertJsonPath('data.precio_unitario', 950)
            ->assertJsonPath('data.subtotal', 1900)
            ->assertJsonPath('data.expira_en', $now->copy()->addMinutes(15)->toAtomString());

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $response->json('data.id'))
            ->assertJsonPath('total', 1900);

        // The units are held, not sold: the stock does not move.
        $this->assertSame(5, $this->stock());
    }

    /** What one cliente holds is not available for the other, and each one only sees its own reservas. */
    public function test_reserved_units_leave_the_catalog_of_the_other_cliente(): void
    {
        $this->actAs($this->ana);
        $this->postJson(self::ENDPOINT, $this->body(2))->assertCreated();

        $this->actAs($this->beto);
        $this->getJson('/api/v1/catalogo')->assertOk()->assertJsonPath('data.0.disponible', 3)->assertJsonPath('data.0.agotado', false);
        $this->getJson(self::ENDPOINT)->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('total', 0);
    }

    /** Of two clientes asking for the last units, the first one gets them and the second one gets a 409. */
    public function test_two_clientes_competing_for_the_last_units_one_wins_and_the_other_gets_a_conflict(): void
    {
        $this->actAs($this->ana);
        $this->postJson(self::ENDPOINT, $this->body(5))->assertCreated();

        $this->actAs($this->beto);
        $this->postJson(self::ENDPOINT, $this->body(1))
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', self::CONFLICTO)
            ->assertJsonPath('detail', 'No se pueden reservar 1 unidades de Leche entera 1 L: solo quedan 0 disponibles.');

        $this->getJson('/api/v1/catalogo')->assertOk()->assertJsonPath('data.0.disponible', 0)->assertJsonPath('data.0.agotado', true);

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame(1, DB::table('reservas')->count());
    }

    /** The second cliente can still take what the first one left free, and not one unit more. */
    public function test_the_other_cliente_takes_only_what_is_left(): void
    {
        $this->actAs($this->ana);
        $this->postJson(self::ENDPOINT, $this->body(3))->assertCreated();

        $this->actAs($this->beto);
        $this->postJson(self::ENDPOINT, $this->body(3))
            ->assertConflict()
            ->assertJsonPath('detail', 'No se pueden reservar 3 unidades de Leche entera 1 L: solo quedan 2 disponibles.');
        $this->postJson(self::ENDPOINT, $this->body(2))->assertCreated();
    }

    /** Reserving the same product again replaces the reserva: the units do not add up. */
    public function test_reserving_again_replaces_the_previous_reserva(): void
    {
        $this->actAs($this->ana);
        $this->postJson(self::ENDPOINT, $this->body(2))->assertCreated();
        $this->postJson(self::ENDPOINT, $this->body(5))->assertCreated();

        $this->getJson(self::ENDPOINT)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.cantidad', 5);
    }

    /** A replacement that does not fit changes nothing: the cliente keeps what it already held. */
    public function test_a_replacement_that_does_not_fit_keeps_the_previous_reserva(): void
    {
        $this->actAs($this->ana);
        $this->postJson(self::ENDPOINT, $this->body(2))->assertCreated();
        $this->postJson(self::ENDPOINT, $this->body(6))->assertConflict();

        $this->getJson(self::ENDPOINT)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.cantidad', 2);
    }

    /** After 15 minutes the reserva stops counting by itself: the units are free and the cliente no longer sees it. */
    public function test_an_expired_reserva_releases_its_units(): void
    {
        $this->actAs($this->ana);
        $this->postJson(self::ENDPOINT, $this->body(5))->assertCreated();

        $this->travel(14)->minutes();
        $this->actAs($this->beto);
        $this->postJson(self::ENDPOINT, $this->body(1))->assertConflict();

        $this->travel(2)->minutes();
        $this->getJson('/api/v1/catalogo')->assertOk()->assertJsonPath('data.0.disponible', 5);
        $this->postJson(self::ENDPOINT, $this->body(5))->assertCreated();

        $this->actAs($this->ana);
        $this->getJson(self::ENDPOINT)->assertOk()->assertJsonCount(0, 'data');
    }

    /** Cancelling a reserva frees its units at once. */
    public function test_cancelling_a_reserva_releases_its_units(): void
    {
        $this->actAs($this->ana);
        $id = $this->postJson(self::ENDPOINT, $this->body(5))->assertCreated()->json('data.id');

        $this->deleteJson(self::ENDPOINT.'/'.$id)->assertNoContent();
        $this->deleteJson(self::ENDPOINT.'/'.$id)->assertNotFound()->assertJsonPath('type', self::NO_ENCONTRADO);

        $this->actAs($this->beto);
        $this->postJson(self::ENDPOINT, $this->body(5))->assertCreated();
    }

    /** A cliente cannot cancel the reserva of another cliente: it answers like a missing one and stays. */
    public function test_cliente_cannot_cancel_the_reserva_of_another_cliente(): void
    {
        $this->actAs($this->ana);
        $id = $this->postJson(self::ENDPOINT, $this->body(5))->assertCreated()->json('data.id');

        $this->actAs($this->beto);
        $this->deleteJson(self::ENDPOINT.'/'.$id)->assertNotFound()->assertJsonPath('type', self::NO_ENCONTRADO);
        $this->postJson(self::ENDPOINT, $this->body(1))->assertConflict();
    }

    /** The till cannot sell what a cliente holds, until the reserva expires. */
    public function test_the_till_cannot_sell_reserved_units(): void
    {
        $this->actAs($this->ana);
        $this->postJson(self::ENDPOINT, $this->body(4))->assertCreated();

        $cashier = UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id]);
        Sanctum::actingAs($cashier, Role::Cajero->abilities());
        $sale = ['lineas' => [['producto_id' => $this->leche->id, 'cantidad' => 2]]];

        $this->postJson('/api/v1/ventas', $sale)
            ->assertConflict()
            ->assertJsonPath('detail', 'No alcanza el stock de Leche entera 1 L. Disponible para vender: 1.');

        $this->travel(16)->minutes();
        $this->postJson('/api/v1/ventas', $sale)->assertCreated();
    }

    /** Units marked as expired cannot be reserved. */
    public function test_expired_units_cannot_be_reserved(): void
    {
        $keeper = UserModel::factory()->role(Role::Bodeguero)->create(['negocio_id' => $this->negocio->id]);
        $this->scopeQueriesTo($this->negocio->id);
        DB::table('unidades_vencidas')->insert([
            'id' => (string) Str::uuid7(),
            'negocio_id' => $this->negocio->id,
            'producto_id' => $this->leche->id,
            'cantidad' => 3,
            'motivo' => 'Encontrada vencida en el estante',
            'estado' => 'activa',
            'usuario_id' => $keeper->id,
        ]);
        $this->actAs($this->ana);

        $this->postJson(self::ENDPOINT, $this->body(3))
            ->assertConflict()
            ->assertJsonPath('detail', 'No se pueden reservar 3 unidades de Leche entera 1 L: solo quedan 2 disponibles.');
        $this->postJson(self::ENDPOINT, $this->body(2))->assertCreated();
    }

    /** A product of another negocio, or one that does not exist, answers 404 and holds nothing. */
    public function test_product_of_another_negocio_cannot_be_reserved(): void
    {
        $foreign = ProductModel::factory()->create(['negocio_id' => NegocioModel::factory()->create()->id, 'stock' => 10]);
        $this->actAs($this->ana);

        foreach ([$foreign->id, Str::uuid()->toString()] as $productId) {
            $this->postJson(self::ENDPOINT, ['producto_id' => $productId, 'cantidad' => 1])
                ->assertNotFound()
                ->assertJsonPath('type', self::NO_ENCONTRADO);
        }
    }

    /**
     * A reserva needs a product and a whole quantity between 1 and 100.
     *
     * @param  array<string, mixed>  $body
     */
    #[DataProvider('invalidBodies')]
    public function test_invalid_reserva_is_rejected(array $body, string $field): void
    {
        $this->actAs($this->ana);

        $this->postJson(self::ENDPOINT, $body)
            ->assertUnprocessable()
            ->assertJsonStructure(['errores' => [$field]]);
    }

    /**
     * Bodies that must be refused and the field each one fails on.
     *
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidBodies(): array
    {
        $product = '019a0000-0000-7000-8000-00000000000a';

        return [
            'sin producto' => [['cantidad' => 1], 'producto_id'],
            'producto que no es uuid' => [['producto_id' => 'leche', 'cantidad' => 1], 'producto_id'],
            'sin cantidad' => [['producto_id' => $product], 'cantidad'],
            'cantidad cero' => [['producto_id' => $product, 'cantidad' => 0], 'cantidad'],
            'cantidad con decimales' => [['producto_id' => $product, 'cantidad' => 1.5], 'cantidad'],
            'cantidad muy grande' => [['producto_id' => $product, 'cantidad' => 101], 'cantidad'],
        ];
    }

    /** The product row is read with FOR UPDATE, which is what serializes two clientes in production. */
    public function test_the_product_row_is_locked_before_the_units_are_held(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });
        $this->actAs($this->ana);

        $this->postJson(self::ENDPOINT, $this->body(1))->assertCreated();

        $locks = array_filter($queries, fn (string $sql): bool => str_contains($sql, 'from "productos"') && str_contains($sql, 'for update'));
        $this->assertNotEmpty($locks, 'The product row was not locked with FOR UPDATE.');
    }

    /** Act as a cliente with the abilities of its role. */
    private function actAs(UserModel $cliente): void
    {
        Sanctum::actingAs($cliente, Role::Cliente->abilities());
    }

    /**
     * The body of a reserva of the product of the test.
     *
     * @return array{producto_id: string, cantidad: int}
     */
    private function body(int $quantity): array
    {
        return ['producto_id' => $this->leche->id, 'cantidad' => $quantity];
    }

    /** Units of the product in the stock. */
    private function stock(): int
    {
        $this->scopeQueriesTo($this->negocio->id);

        return (int) DB::table('productos')->where('id', $this->leche->id)->value('stock');
    }
}
