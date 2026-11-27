<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use Illuminate\Database\QueryException;
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

/** The cashier collects at the register: in cash, by card on the datáfono or by a SINPE transfer received. */
final class RegisterPaymentTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private const string ENDPOINT = '/api/v1/ventas';

    private NegocioModel $negocio;

    private ProductModel $arroz;

    /** A negocio with a product of 10 units at 1500 and its cashier, on a fixed day. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-23 10:00:00');
        $this->negocio = NegocioModel::factory()->create();
        $this->arroz = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'precio_venta' => '1500.00', 'stock' => 10]);
        Sanctum::actingAs(UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id]), Role::Cajero->abilities());
    }

    /**
     * Card on the datáfono and SINPE received both close the sale with their way of paying and their reference.
     *
     * @return array<string, array{string, string}>
     */
    public static function referencedMethods(): array
    {
        return ['tarjeta en el datáfono' => ['tarjeta', 'AUT-482913'], 'SINPE recibido' => ['sinpe_movil', '2026112300123']];
    }

    /** The sale is paid with the way and the reference of the register, and it says it was taken there. */
    #[DataProvider('referencedMethods')]
    public function test_register_payment_keeps_method_reference_and_origin(string $method, string $reference): void
    {
        $id = $this->openSale();

        $this->postJson(self::ENDPOINT.'/'.$id.'/cobrar', ['metodo_pago' => $method, 'referencia' => $reference])
            ->assertOk()
            ->assertJsonPath('data.estado', 'cobrada')
            ->assertJsonPath('data.metodo_pago', $method)
            ->assertJsonPath('data.referencia_pago', $reference)
            ->assertJsonPath('data.origen_pago', 'caja');

        $this->getJson(self::ENDPOINT.'/'.$id)->assertJsonPath('data.referencia_pago', $reference);
    }

    /** Without a body the sale is still paid in cash, as before. */
    public function test_without_a_body_the_sale_is_paid_in_cash(): void
    {
        $id = $this->openSale();

        $this->postJson(self::ENDPOINT.'/'.$id.'/cobrar')
            ->assertOk()
            ->assertJsonPath('data.metodo_pago', 'efectivo')
            ->assertJsonPath('data.referencia_pago', null)
            ->assertJsonPath('data.origen_pago', 'caja');
    }

    /**
     * A card or SINPE payment needs its reference, cash takes none, and the way of paying must be one of the three.
     *
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidBodies(): array
    {
        return [
            'tarjeta sin referencia' => [['metodo_pago' => 'tarjeta'], 'referencia'],
            'SINPE sin referencia' => [['metodo_pago' => 'sinpe_movil'], 'referencia'],
            'referencia vacía' => [['metodo_pago' => 'tarjeta', 'referencia' => ''], 'referencia'],
            'referencia demasiado larga' => [['metodo_pago' => 'tarjeta', 'referencia' => '12345678901234567890123456789012345678901234567890123456789012345'], 'referencia'],
            'efectivo con referencia' => [['metodo_pago' => 'efectivo', 'referencia' => 'AUT-1'], 'referencia'],
            'referencia sin método' => [['referencia' => 'AUT-1'], 'referencia'],
            'fiado por este camino' => [['metodo_pago' => 'fiado'], 'metodo_pago'],
            'método desconocido' => [['metodo_pago' => 'cheque'], 'metodo_pago'],
        ];
    }

    /**
     * An invalid body is a 422 and the sale stays open.
     *
     * @param  array<string, string>  $body
     */
    #[DataProvider('invalidBodies')]
    public function test_invalid_body_is_rejected_and_the_sale_stays_open(array $body, string $field): void
    {
        $id = $this->openSale();

        $this->postJson(self::ENDPOINT.'/'.$id.'/cobrar', $body)->assertUnprocessable()->assertJsonValidationErrors($field, 'errores');

        $this->getJson(self::ENDPOINT.'/'.$id)->assertJsonPath('data.estado', 'abierta');
    }

    /** A sale cannot be collected twice: the second answer is a 409 and the first reference stays. */
    public function test_the_same_sale_cannot_be_collected_twice(): void
    {
        $id = $this->openSale();
        $this->postJson(self::ENDPOINT.'/'.$id.'/cobrar', ['metodo_pago' => 'tarjeta', 'referencia' => 'AUT-1'])->assertOk();

        $this->postJson(self::ENDPOINT.'/'.$id.'/cobrar', ['metodo_pago' => 'tarjeta', 'referencia' => 'AUT-2'])->assertConflict();

        $this->getJson(self::ENDPOINT.'/'.$id)->assertJsonPath('data.referencia_pago', 'AUT-1');
    }

    /** A cancelled sale cannot be collected. */
    public function test_a_cancelled_sale_cannot_be_collected(): void
    {
        $id = $this->openSale();
        $this->postJson(self::ENDPOINT.'/'.$id.'/anular')->assertOk();

        $this->postJson(self::ENDPOINT.'/'.$id.'/cobrar', ['metodo_pago' => 'sinpe_movil', 'referencia' => '123'])->assertConflict();
    }

    /** The listing tells a card payment taken at the register from one that went through a provider. */
    public function test_listing_tells_the_register_from_the_provider(): void
    {
        $atTheRegister = $this->openSale();
        $this->postJson(self::ENDPOINT.'/'.$atTheRegister.'/cobrar', ['metodo_pago' => 'tarjeta', 'referencia' => 'AUT-9'])->assertOk();

        $throughTheProvider = $this->openSale();
        $this->scopeQueriesTo($this->negocio->id);
        DB::table('ventas')->where('id', $throughTheProvider)->update(['estado' => 'cobrada', 'metodo_pago' => 'tarjeta', 'cobrada_at' => now()]);

        $origins = collect($this->getJson(self::ENDPOINT)->assertOk()->json('data'))->pluck('origen_pago', 'id');

        $this->assertSame('caja', $origins[$atTheRegister]);
        $this->assertSame('pasarela', $origins[$throughTheProvider]);
    }

    /** A reference only goes with card or SINPE: the database refuses one on a cash sale. */
    public function test_the_database_refuses_a_reference_on_a_cash_sale(): void
    {
        $id = $this->openSale();
        $this->scopeQueriesTo($this->negocio->id);

        $this->expectException(QueryException::class);
        DB::table('ventas')->where('id', $id)->update(['referencia_pago' => 'AUT-1']);
    }

    /** Open a sale of two units of rice and give back its id. */
    private function openSale(): string
    {
        return (string) $this->postJson(self::ENDPOINT, ['lineas' => [['producto_id' => $this->arroz->id, 'cantidad' => 2]]])
            ->assertCreated()
            ->json('data.id');
    }
}
