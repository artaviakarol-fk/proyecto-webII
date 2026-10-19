<?php

declare(strict_types=1);

namespace Tests\Support\Shared;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Src\Shared\Infrastructure\Persistence\RowLevelSecurity;

/**
 * Create, for one test, a table of the negocios protected exactly as a migration would protect it.
 *
 * The domain tables (productos, ventas...) arrive with their own issues; until then this table
 * proves the isolation mechanism every one of them will use.
 */
trait CreatesATableOfTheNegocios
{
    protected const string TABLE = 'recursos_de_prueba';

    /** Create the table as the owner of the schema and drop it once the test transaction is rolled back. */
    protected function createTableOfTheNegocios(): void
    {
        $this->asOwner(function (): void {
            Schema::create(self::TABLE, function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('negocio_id')->constrained('negocios')->cascadeOnDelete();
                $table->string('nombre', 60);
                $table->timestampsTz();
            });
            RowLevelSecurity::enable(self::TABLE);
        });

        $this->beforeApplicationDestroyed(fn () => $this->asOwner(fn () => Schema::dropIfExists(self::TABLE)));
    }

    /** Run DDL through the owner connection, the way migrate --database=pgsql_admin does. */
    private function asOwner(callable $callback): void
    {
        $default = DB::getDefaultConnection();
        DB::setDefaultConnection('pgsql_admin');

        try {
            $callback();
        } finally {
            DB::setDefaultConnection($default);
        }
    }
}
