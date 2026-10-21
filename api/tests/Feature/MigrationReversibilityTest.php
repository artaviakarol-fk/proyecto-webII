<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class MigrationReversibilityTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    /** Every migration rolls back and applies again without leftovers. */
    public function test_migrations_roll_back_and_apply_again(): void
    {
        $schema = Schema::connection('pgsql_admin');
        $migrated = $schema->getTableListing(schemaQualified: false);

        $this->artisan('migrate:reset', ['--database' => 'pgsql_admin'])->assertSuccessful();

        $this->assertSame(['migrations'], $schema->getTableListing(schemaQualified: false));

        $this->artisan('migrate', ['--database' => 'pgsql_admin'])->assertSuccessful();

        $this->assertSame($migrated, $schema->getTableListing(schemaQualified: false));
    }
}
