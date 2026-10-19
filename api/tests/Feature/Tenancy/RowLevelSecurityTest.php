<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Src\Shared\Infrastructure\Persistence\RowLevelSecurity;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\CreatesATableOfTheNegocios;
use Tests\Support\Shared\ScopesQueriesToNegocio;
use Tests\TestCase;

final class RowLevelSecurityTest extends TestCase
{
    use CreatesATableOfTheNegocios, RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private const string POLICY_EXPRESSION = "(negocio_id = (NULLIF(current_setting('app.negocio_id'::text, true), ''::text))::uuid)";

    private NegocioModel $negocio;

    private NegocioModel $otherNegocio;

    /** Give two negocios one row each in a table protected by row-level security. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTableOfTheNegocios();
        $this->negocio = NegocioModel::factory()->create();
        $this->otherNegocio = NegocioModel::factory()->create();

        foreach ([$this->negocio, $this->otherNegocio] as $negocio) {
            $this->scopeQueriesTo($negocio->id);
            DB::table(self::TABLE)->insert(['id' => (string) Str::uuid7(), 'negocio_id' => $negocio->id, 'nombre' => 'Arroz']);
        }
    }

    /** The tests run as the application role, the same one the requests use. */
    public function test_queries_run_as_the_application_role(): void
    {
        $this->assertSame(config('database.connections.pgsql.username'), DB::scalar('select current_user'));
    }

    /** Every table with a negocio_id column, except the accounts, has row-level security enabled, forced and its policy. */
    public function test_every_table_of_the_negocios_is_protected_by_its_policy(): void
    {
        $tables = array_column(DB::select(
            "select table_name from information_schema.columns
             where table_schema = 'public' and column_name = 'negocio_id' and table_name <> 'users'",
        ), 'table_name');

        $this->assertContains(self::TABLE, $tables);

        foreach ($tables as $table) {
            $security = DB::selectOne('select relrowsecurity, relforcerowsecurity from pg_class where oid = ?::regclass', [$table]);
            $policies = DB::select(
                "select policyname, cmd, qual, with_check from pg_policies where schemaname = 'public' and tablename = ?",
                [$table],
            );

            $this->assertTrue($security->relrowsecurity, "Row Level Security no está activado en {$table}.");
            $this->assertTrue($security->relforcerowsecurity, "Row Level Security no está forzado en {$table}.");
            $this->assertEquals([(object) [
                'policyname' => RowLevelSecurity::policyName($table),
                'cmd' => 'ALL',
                'qual' => self::POLICY_EXPRESSION,
                'with_check' => self::POLICY_EXPRESSION,
            ]], $policies, "Falta la política de aislamiento de {$table} o es distinta.");
        }
    }

    /** Without a negocio in the transaction no row is visible and no update or delete reaches one. */
    public function test_without_a_negocio_no_row_is_visible_or_changed(): void
    {
        $this->scopeQueriesTo(null);

        $this->assertSame(0, DB::table(self::TABLE)->count());
        $this->assertSame(0, DB::table(self::TABLE)->update(['nombre' => 'Cambiado']));
        $this->assertSame(0, DB::table(self::TABLE)->delete());

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame(1, DB::table(self::TABLE)->where('nombre', 'Arroz')->count());
    }

    /** A query with no filter at all returns only the rows of the negocio set for the transaction. */
    public function test_unfiltered_query_returns_only_the_rows_of_the_negocio(): void
    {
        foreach ([$this->negocio, $this->otherNegocio] as $negocio) {
            $this->scopeQueriesTo($negocio->id);

            $this->assertSame([$negocio->id], DB::table(self::TABLE)->pluck('negocio_id')->all());
        }
    }

    /** Inserting a row for another negocio fails with the row-level security error. */
    public function test_inserting_a_row_for_another_negocio_fails(): void
    {
        $this->scopeQueriesTo($this->negocio->id);

        $this->assertRowLevelSecurityViolation(fn () => DB::table(self::TABLE)->insert([
            'id' => (string) Str::uuid7(),
            'negocio_id' => $this->otherNegocio->id,
            'nombre' => 'Frijoles',
        ]));
    }

    /** Moving a row to another negocio fails and the row stays where it was. */
    public function test_moving_a_row_to_another_negocio_fails(): void
    {
        $this->scopeQueriesTo($this->negocio->id);

        $this->assertRowLevelSecurityViolation(
            fn () => DB::table(self::TABLE)->update(['negocio_id' => $this->otherNegocio->id]),
        );
        $this->assertSame([$this->negocio->id], DB::table(self::TABLE)->pluck('negocio_id')->all());
    }

    /** Run a write inside a savepoint and expect PostgreSQL to reject it by its row-level security policy. */
    private function assertRowLevelSecurityViolation(Closure $write): void
    {
        try {
            DB::transaction($write);
            $this->fail('Una fila pasó a otro negocio.');
        } catch (QueryException $exception) {
            $this->assertSame('42501', $exception->getCode());
            $this->assertStringContainsString('violates row-level security policy', $exception->getMessage());
        }
    }
}
