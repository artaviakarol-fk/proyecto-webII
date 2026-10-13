<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Src\Shared\Domain\Contracts\DatabaseStatusChecker;
use Tests\TestCase;

final class HealthCheckTest extends TestCase
{
    /** The framework health route reports the application as up. */
    public function test_framework_health_route_reports_the_application_as_up(): void
    {
        $this->get('/up')->assertOk();
    }

    /** The API health route reports the application and its database. */
    public function test_health_route_reports_the_application_and_the_database(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.app', 'ok')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonStructure(['status', 'checks' => ['app', 'database'], 'checked_at']);
    }

    /** An unavailable database degrades the report but still answers 200. */
    public function test_unavailable_database_degrades_the_report(): void
    {
        $this->app->instance(DatabaseStatusChecker::class, new class implements DatabaseStatusChecker
        {
            public function isAvailable(): bool
            {
                return false;
            }
        });

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.app', 'ok')
            ->assertJsonPath('checks.database', 'unavailable');
    }
}
