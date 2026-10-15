<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class DatabaseRolesTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    /** The application role is neither superuser nor able to skip row-level security. */
    public function test_application_role_has_no_elevated_attributes(): void
    {
        $role = DB::selectOne('select rolsuper, rolbypassrls, rolcreatedb, rolcreaterole from pg_roles where rolname = current_user');

        $this->assertNotNull($role);
        $this->assertSame([false, false, false, false], array_values((array) $role));
    }

    /** The application role owns no table, so the policies of every table apply to it. */
    public function test_application_role_owns_no_table(): void
    {
        $owned = DB::scalar("select count(*) from pg_tables where schemaname = 'public' and tableowner = current_user");

        $this->assertSame(0, $owned);
    }

    /** The application role reads and writes the tables the owner created. */
    public function test_application_role_reads_and_writes_the_tables(): void
    {
        DB::table('cache')->insert(['key' => 'prueba-de-rol', 'value' => 'ok', 'expiration' => 0]);

        $this->assertSame('ok', DB::table('cache')->where('key', 'prueba-de-rol')->value('value'));
    }

    /** The application role cannot change the schema. */
    public function test_application_role_cannot_change_the_schema(): void
    {
        $this->expectException(QueryException::class);

        DB::statement('create table prohibida (id integer)');
    }
}
