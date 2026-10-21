<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Src\Shared\Domain\Contracts\NegocioContext;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\CreatesATableOfTheNegocios;
use Tests\Support\Shared\ScopesQueriesToNegocio;
use Tests\TestCase;

/**
 * El negocio A no puede leer, editar ni borrar recursos del negocio B cambiando el ID, ni crear
 * recursos a nombre de B mandando su negocio_id: el negocio sale siempre de la cuenta del token.
 *
 * Las rutas de prueba consultan la tabla SIN filtrar por negocio_id a propósito: lo que aísla
 * es la política de Row Level Security de PostgreSQL, igual que en los módulos reales.
 */
final class NegocioOfTheAuthenticatedUserTest extends TestCase
{
    use CreatesATableOfTheNegocios, RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private const string ENDPOINT = '/api/v1/pruebas/recursos';

    private const string MISSING_ID = '019a0000-7b1e-7c3a-9f1d-2b6a4e8c0d99';

    private NegocioModel $negocio;

    private NegocioModel $otherNegocio;

    private string $ownRow;

    private string $foreignRow;

    /** Authenticate an account of negocio A and give both negocios one row. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTableOfTheNegocios();
        $this->registerRoutes();

        $this->negocio = NegocioModel::factory()->create(['nombre' => 'Minisúper A']);
        $this->otherNegocio = NegocioModel::factory()->create(['nombre' => 'Minisúper B']);
        $this->ownRow = $this->rowOf($this->negocio, 'Arroz de A');
        $this->foreignRow = $this->rowOf($this->otherNegocio, 'Arroz de B');

        Sanctum::actingAs(UserModel::factory()->create(['negocio_id' => $this->negocio->id]), ['*']);
    }

    /** A listing shows the rows of the own negocio and none of the other. */
    public function test_listing_shows_only_the_rows_of_the_own_negocio(): void
    {
        $this->getJson(self::ENDPOINT.'?negocio_id='.$this->otherNegocio->id)
            ->assertOk()
            ->assertExactJson(['data' => [['id' => $this->ownRow, 'nombre' => 'Arroz de A']]]);
    }

    /** Reading, editing or deleting a row of the other negocio answers the same 404 as a row that does not exist. */
    #[DataProvider('accessesById')]
    public function test_foreign_row_answers_like_a_missing_one(string $method, array $payload): void
    {
        $foreign = $this->json($method, self::ENDPOINT.'/'.$this->foreignRow, $payload);
        $missing = $this->json($method, self::ENDPOINT.'/'.self::MISSING_ID, $payload);

        $foreign
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'http://localhost/problemas/no-encontrado');
        $this->assertSame(Arr::except($missing->json(), 'instance'), Arr::except($foreign->json(), 'instance'));

        $this->scopeQueriesTo($this->otherNegocio->id);
        $this->assertSame('Arroz de B', DB::table(self::TABLE)->where('id', $this->foreignRow)->value('nombre'));
    }

    /**
     * Requests that reach a row by its identifier and their payload.
     *
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function accessesById(): array
    {
        return [
            'leer' => ['GET', []],
            'editar' => ['PATCH', ['nombre' => 'Robado']],
            'borrar' => ['DELETE', []],
        ];
    }

    /** The own rows are still read, edited and deleted normally. */
    public function test_own_row_can_be_read_edited_and_deleted(): void
    {
        $this->getJson(self::ENDPOINT.'/'.$this->ownRow)->assertOk()->assertJsonPath('data.nombre', 'Arroz de A');
        $this->patchJson(self::ENDPOINT.'/'.$this->ownRow, ['nombre' => 'Arroz integral'])->assertOk();
        $this->deleteJson(self::ENDPOINT.'/'.$this->ownRow)->assertNoContent();
        $this->getJson(self::ENDPOINT.'/'.$this->ownRow)->assertNotFound();
    }

    /** A new row belongs to the negocio of the account, whatever negocio the body and the query string name. */
    public function test_created_row_belongs_to_the_negocio_of_the_account(): void
    {
        $other = $this->otherNegocio->id;

        $id = $this->postJson(self::ENDPOINT."?negocio_id={$other}", ['nombre' => 'Frijoles', 'negocio_id' => $other])
            ->assertCreated()
            ->json('data.id');

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame($this->negocio->id, DB::table(self::TABLE)->where('id', $id)->value('negocio_id'));

        $this->scopeQueriesTo($other);
        $this->assertSame(1, DB::table(self::TABLE)->count());
    }

    /** The profile endpoint shows the negocio of the account even when the query string names another one. */
    public function test_profile_ignores_a_negocio_in_the_query_string(): void
    {
        $this->getJson('/api/v1/negocio?negocio_id='.$this->otherNegocio->id)
            ->assertOk()
            ->assertJsonPath('data.id', $this->negocio->id)
            ->assertJsonPath('data.nombre', 'Minisúper A');
    }

    /** End to end: a real token of negocio A, sent in the header, cannot read the row of negocio B. */
    public function test_a_real_token_of_one_negocio_cannot_reach_the_other(): void
    {
        $this->app['auth']->forgetGuards();
        UserModel::factory()->create(['negocio_id' => $this->negocio->id, 'correo' => 'caja@a.test']);
        $token = $this->postJson('/api/v1/tokens', [
            'correo' => 'caja@a.test',
            'contrasena' => 'password',
            'dispositivo' => 'Caja 1',
        ])->assertCreated()->json('data.token');

        $headers = ['Authorization' => "Bearer {$token}"];

        $this->getJson(self::ENDPOINT.'/'.$this->foreignRow, $headers)->assertNotFound();
        $this->getJson(self::ENDPOINT.'/'.$this->ownRow, $headers)->assertOk();
    }

    /** Insert a row for a negocio, under that negocio as its policy demands. */
    private function rowOf(NegocioModel $negocio, string $name): string
    {
        $id = (string) Str::uuid7();
        $this->scopeQueriesTo($negocio->id);
        DB::table(self::TABLE)->insert(['id' => $id, 'negocio_id' => $negocio->id, 'nombre' => $name]);

        return $id;
    }

    /** Throwaway CRUD routes that never filter by negocio themselves. */
    private function registerRoutes(): void
    {
        Route::prefix('api/v1/pruebas/recursos')->middleware(['api', 'auth:sanctum', 'negocio'])->group(function (): void {
            Route::get('/', fn () => ['data' => DB::table(self::TABLE)->get(['id', 'nombre'])]);

            Route::post('/', function (Request $request, NegocioContext $context) {
                $id = (string) Str::uuid7();
                DB::table(self::TABLE)->insert([
                    'id' => $id,
                    'negocio_id' => $context->current()->value,
                    'nombre' => $request->string('nombre')->toString(),
                ]);

                return response()->json(['data' => ['id' => $id]], 201);
            });

            Route::get('{id}', fn (string $id) => ['data' => DB::table(self::TABLE)->where('id', $id)->first(['id', 'nombre']) ?? abort(404)]);

            Route::patch('{id}', function (Request $request, string $id) {
                DB::table(self::TABLE)->where('id', $id)->update(['nombre' => $request->string('nombre')->toString()]) > 0 || abort(404);

                return ['data' => ['id' => $id]];
            });

            Route::delete('{id}', function (string $id) {
                DB::table(self::TABLE)->where('id', $id)->delete() > 0 || abort(404);

                return response()->noContent();
            });
        });
    }
}
