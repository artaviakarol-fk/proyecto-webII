<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Shared\Infrastructure\Tenancy\NegocioTransaction;

/**
 * Row Level Security de PostgreSQL para las tablas que pertenecen a un negocio.
 *
 * Toda tabla con columna negocio_id se protege desde su migración:
 *
 *     RowLevelSecurity::enable('productos');   // en up()
 *     RowLevelSecurity::disable('productos');  // en down(), antes de borrar la tabla
 *
 * La política solo deja ver, insertar, cambiar o borrar las filas del negocio que la
 * transacción tiene fijado en app.negocio_id. Sin negocio fijado no se ve ninguna fila.
 */
final class RowLevelSecurity
{
    /** Enable, force and attach the isolation policy to a table of the negocios. */
    public static function enable(string $table): void
    {
        $policy = self::policyName($table);
        $condition = self::condition();

        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        DB::statement("CREATE POLICY {$policy} ON {$table} USING ({$condition}) WITH CHECK ({$condition})");
    }

    /** Drop the policy and turn row-level security off again. */
    public static function disable(string $table): void
    {
        $policy = self::policyName($table);

        DB::statement("DROP POLICY IF EXISTS {$policy} ON {$table}");
        DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
    }

    /** Name of the isolation policy of a table. */
    public static function policyName(string $table): string
    {
        return "{$table}_aislamiento_negocio";
    }

    /** Rows are reachable only when their negocio is the one set for the transaction. */
    private static function condition(): string
    {
        $setting = NegocioTransaction::SETTING;

        return "negocio_id = NULLIF(current_setting('{$setting}', true), '')::uuid";
    }
}
