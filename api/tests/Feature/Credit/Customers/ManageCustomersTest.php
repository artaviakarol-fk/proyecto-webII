<?php

declare(strict_types=1);

namespace Tests\Feature\Credit\Customers;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Credit\Customers\Infrastructure\Persistence\Models\CustomerModel;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToNegocio;
use Tests\TestCase;

/** US-302: the administrator registers the trusted customers and their limit of fiado. */
final class ManageCustomersTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private const string ENDPOINT = '/api/v1/clientes';

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

    /** A new customer gets the base limit of ₡90 000, owes nothing and belongs to the negocio of the account. */
    public function test_administrator_registers_a_customer_with_the_base_limit(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'nombre' => 'Doña María',
            'telefono' => '8888-0001',
            'negocio_id' => $this->otherNegocio->id,
        ]);

        $id = $response->json('data.id');
        $response
            ->assertCreated()
            ->assertHeader('Location', 'http://localhost'.self::ENDPOINT.'/'.$id)
            ->assertExactJson(['data' => [
                'id' => $id,
                'nombre' => 'Doña María',
                'telefono' => '8888-0001',
                'limite_fiado' => 90000,
                'saldo_actual' => 0,
                'activo' => true,
            ]]);

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseHas('clientes', ['id' => $id, 'negocio_id' => $this->negocio->id, 'limite_fiado' => '90000.00', 'saldo_actual' => '0.00']);
    }

    /** The administrator can give a customer a lower limit, and the phone is optional. */
    public function test_limit_can_be_lowered_and_phone_is_optional(): void
    {
        $this->postJson(self::ENDPOINT, ['nombre' => 'Don José', 'limite_fiado' => '25000.50'])
            ->assertCreated()
            ->assertJsonPath('data.telefono', null)
            ->assertJsonPath('data.limite_fiado', 25000.5);
    }

    /** The base limit is the most the administration can set; going above it is the exceptional increase. */
    public function test_limit_above_the_base_limit_is_rejected(): void
    {
        $this->postJson(self::ENDPOINT, ['nombre' => 'Don José', 'limite_fiado' => '90000.01'])
            ->assertUnprocessable()
            ->assertJsonPath('type', 'http://localhost/problemas/datos-invalidos')
            ->assertJsonPath('detail', 'El límite de fiado no puede pasar de ₡90 000. Para casos de emergencia está la ampliación del límite.');

        $this->assertDatabaseCount('clientes', 0);
    }

    /** The base limit comes from the configuration (LIMITE_FIADO_BASE), not from the code. */
    public function test_base_limit_is_configurable(): void
    {
        config(['credit.base_limit' => '50000']);

        $this->postJson(self::ENDPOINT, ['nombre' => 'Ana'])->assertCreated()->assertJsonPath('data.limite_fiado', 50000);
        $this->postJson(self::ENDPOINT, ['nombre' => 'Luis', 'limite_fiado' => 60000])->assertUnprocessable();
    }

    /** Invalid data is rejected field by field. */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_data_is_rejected(array $payload, string $field): void
    {
        $this->postJson(self::ENDPOINT, ['nombre' => 'Ana', ...$payload])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor($field, 'errores');
    }

    /**
     * Payloads with one invalid field, and that field.
     *
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'sin nombre' => [['nombre' => ''], 'nombre'],
            'nombre muy largo' => [['nombre' => str_repeat('a', 121)], 'nombre'],
            'teléfono con letras' => [['telefono' => '8888-ABCD'], 'telefono'],
            'límite negativo' => [['limite_fiado' => -1], 'limite_fiado'],
            'límite con tres decimales' => [['limite_fiado' => '100.555'], 'limite_fiado'],
            'activo que no es booleano' => [['activo' => 'quizás'], 'activo'],
        ];
    }

    /** Nobody sets the balance through the API: a saldo_actual sent on create or replace is ignored. */
    public function test_balance_sent_by_the_client_is_ignored(): void
    {
        $id = $this->postJson(self::ENDPOINT, ['nombre' => 'Ana', 'saldo_actual' => 50000])
            ->assertCreated()
            ->assertJsonPath('data.saldo_actual', 0)
            ->json('data.id');

        $this->putJson(self::ENDPOINT.'/'.$id, ['nombre' => 'Ana', 'saldo_actual' => 1])
            ->assertOk()
            ->assertJsonPath('data.saldo_actual', 0);

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame('0.00', DB::table('clientes')->where('id', $id)->value('saldo_actual'));
    }

    /** The list shows one page of the customers of the negocio ordered by name, with what each one owes. */
    public function test_listing_is_paginated_and_ordered_by_name(): void
    {
        foreach (['Carmen', 'Ana', 'Luis'] as $name) {
            $this->customer(['nombre' => $name]);
        }
        $this->customer(['nombre' => 'Beatriz'], $this->otherNegocio);

        $this->getJson(self::ENDPOINT.'?por_pagina=2')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Ana', 'Carmen'])
            ->assertJsonPath('data.0.saldo_actual', 0)
            ->assertJsonPath('meta', ['pagina' => 1, 'por_pagina' => 2, 'total' => 3, 'ultima_pagina' => 2]);
    }

    /** The search matches part of the name, ignoring case, or part of the phone; the filter keeps active or inactive ones. */
    public function test_search_and_active_filter(): void
    {
        $this->customer(['nombre' => 'María Rodríguez', 'telefono' => '8888-0001']);
        $this->customer(['nombre' => 'María Solís', 'telefono' => '8888-0002', 'activo' => false]);
        $this->customer(['nombre' => 'José Alfaro', 'telefono' => '7777-0003']);

        $this->getJson(self::ENDPOINT.'?buscar=maría')->assertOk()->assertJsonPath('data.*.nombre', ['María Rodríguez', 'María Solís']);
        $this->getJson(self::ENDPOINT.'?buscar=7777')->assertOk()->assertJsonPath('data.*.nombre', ['José Alfaro']);
        $this->getJson(self::ENDPOINT.'?activo=1')->assertOk()->assertJsonPath('data.*.nombre', ['José Alfaro', 'María Rodríguez']);
        $this->getJson(self::ENDPOINT.'?activo=0')->assertOk()->assertJsonPath('data.*.nombre', ['María Solís']);
        $this->getJson(self::ENDPOINT.'?buscar=%25')->assertOk()->assertJsonPath('data', []);
    }

    /** One customer is shown by its identifier. */
    public function test_customer_is_shown(): void
    {
        $customer = $this->customer(['nombre' => 'Carmen']);

        $this->getJson(self::ENDPOINT.'/'.$customer->id)
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Carmen')
            ->assertJsonPath('data.limite_fiado', 90000);
    }

    /** PUT replaces the data and the limit of the customer. */
    public function test_administrator_replaces_a_customer(): void
    {
        $customer = $this->customer(['nombre' => 'Carmen']);

        $this->putJson(self::ENDPOINT.'/'.$customer->id, ['nombre' => 'Carmen Mora', 'telefono' => '+506 8888 1234', 'limite_fiado' => 40000])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Carmen Mora')
            ->assertJsonPath('data.telefono', '+506 8888 1234')
            ->assertJsonPath('data.limite_fiado', 40000)
            ->assertJsonPath('data.activo', true);
    }

    /** DELETE only deactivates the customer: the row, its history and its balance stay. */
    public function test_delete_deactivates_the_customer(): void
    {
        $customer = $this->customer(['nombre' => 'Carmen']);

        $this->deleteJson(self::ENDPOINT.'/'.$customer->id)->assertNoContent();

        $this->getJson(self::ENDPOINT.'/'.$customer->id)->assertOk()->assertJsonPath('data.activo', false);

        $this->putJson(self::ENDPOINT.'/'.$customer->id, ['nombre' => 'Carmen', 'activo' => true])
            ->assertOk()
            ->assertJsonPath('data.activo', true);
    }

    /** The cashier sees the customers and what they owe, but cannot register, change or deactivate them. */
    public function test_cashier_only_reads(): void
    {
        $customer = $this->customer();
        $this->actAs(Role::Cajero);

        $this->getJson(self::ENDPOINT)->assertOk();
        $this->getJson(self::ENDPOINT.'/'.$customer->id)->assertOk();

        foreach ([
            $this->postJson(self::ENDPOINT, ['nombre' => 'Ana']),
            $this->putJson(self::ENDPOINT.'/'.$customer->id, ['nombre' => 'Ana', 'limite_fiado' => 1000]),
            $this->deleteJson(self::ENDPOINT.'/'.$customer->id),
        ] as $response) {
            $response->assertForbidden()->assertJsonPath('type', 'http://localhost/problemas/prohibido');
        }
    }

    /** The warehouse keeper has nothing to do with fiado. */
    public function test_warehouse_keeper_cannot_see_the_customers(): void
    {
        $this->actAs(Role::Bodeguero);

        $this->getJson(self::ENDPOINT)->assertForbidden();
    }

    /** A customer of another negocio answers the same 404 as a missing one, and stays as it was. */
    #[DataProvider('accessesById')]
    public function test_customer_of_another_negocio_answers_like_a_missing_one(string $method, bool $withPayload): void
    {
        $foreign = $this->customer(['nombre' => 'Cliente de B'], $this->otherNegocio);
        $payload = $withPayload ? ['nombre' => 'Otro', 'limite_fiado' => 100] : [];

        $foreignResponse = $this->json($method, self::ENDPOINT.'/'.$foreign->id, $payload);
        $missingResponse = $this->json($method, self::ENDPOINT.'/'.self::MISSING_ID, $payload);

        $foreignResponse->assertNotFound()->assertJsonPath('type', 'http://localhost/problemas/no-encontrado');
        $this->assertSame(Arr::except($missingResponse->json(), 'instance'), Arr::except($foreignResponse->json(), 'instance'));

        $this->scopeQueriesTo($this->otherNegocio->id);
        $row = DB::table('clientes')->where('id', $foreign->id)->first();
        $this->assertSame('Cliente de B', $row->nombre);
        $this->assertTrue($row->activo);
    }

    /**
     * Requests that reach a customer by its identifier.
     *
     * @return array<string, array{string, bool}>
     */
    public static function accessesById(): array
    {
        return [
            'leer' => ['GET', false],
            'reemplazar' => ['PUT', true],
            'desactivar' => ['DELETE', false],
        ];
    }

    /** Without a token the customers answer 401. */
    public function test_guest_cannot_see_the_customers(): void
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
     * Create a customer, by default of the first negocio.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function customer(array $attributes = [], ?NegocioModel $negocio = null): CustomerModel
    {
        return CustomerModel::factory()->create(['negocio_id' => ($negocio ?? $this->negocio)->id, ...$attributes]);
    }
}
