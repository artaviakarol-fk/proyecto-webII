<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Shared\Infrastructure\Tenancy\NegocioTransaction;
use Tests\TestCase;

final class ScopeRequestToNegocioTest extends TestCase
{
    private const string NEGOCIO_ID = '019a0000-0000-7000-8000-0000000000aa';

    private const string OTHER_NEGOCIO_ID = '019a0000-0000-7000-8000-0000000000bb';

    /** Register a throwaway route that reports the negocio and the depth of its transaction. */
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api/v1/pruebas')->middleware(['api', 'auth:sanctum', 'negocio'])->group(function (): void {
            Route::post('alcance', fn (): array => self::scope());
        });
    }

    /** The request runs in one transaction scoped to the negocio of the account, ignoring a negocio in the request. */
    public function test_request_is_scoped_to_the_negocio_of_the_account(): void
    {
        $this->actAs(self::NEGOCIO_ID);

        $this->postJson('/api/v1/pruebas/alcance?negocio_id='.self::OTHER_NEGOCIO_ID, ['negocio_id' => self::OTHER_NEGOCIO_ID])
            ->assertExactJson(['negocio' => self::NEGOCIO_ID, 'transacciones' => 1]);
    }

    /** The negocio dies with the transaction, so the next use of the connection does not inherit it. */
    public function test_scope_ends_with_the_request_transaction(): void
    {
        $this->actAs(self::NEGOCIO_ID);

        $this->postJson('/api/v1/pruebas/alcance')->assertOk();

        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame('', DB::scalar("select coalesce(current_setting('".NegocioTransaction::SETTING."', true), '')"));
    }

    /** Without a token the request never reaches the database. */
    public function test_guest_is_not_authenticated(): void
    {
        $this->postJson('/api/v1/pruebas/alcance')
            ->assertUnauthorized()
            ->assertJsonPath('type', 'http://localhost/problemas/no-autenticado');
    }

    /**
     * Negocio of the open transaction and how many transactions are open.
     *
     * @return array{negocio: mixed, transacciones: int}
     */
    private static function scope(): array
    {
        return [
            'negocio' => DB::scalar("select current_setting('".NegocioTransaction::SETTING."', true)"),
            'transacciones' => DB::transactionLevel(),
        ];
    }

    /** Authenticate an account built in memory, without touching the database. */
    private function actAs(string $negocioId): void
    {
        Sanctum::actingAs(UserModel::factory()->make(['negocio_id' => $negocioId]), ['*']);
    }
}
