<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Migrate the test database with the owner of the schema, as production does, while the
 * tests themselves run as the application role, so row-level security applies to them.
 */
trait RefreshDatabaseAsOwner
{
    use RefreshDatabase {
        migrateFreshUsing as private frameworkMigrateFreshUsing;
    }

    /**
     * Options for migrate:fresh.
     *
     * @return array<string, mixed>
     */
    protected function migrateFreshUsing(): array
    {
        return [...$this->frameworkMigrateFreshUsing(), '--database' => 'pgsql_admin'];
    }
}
