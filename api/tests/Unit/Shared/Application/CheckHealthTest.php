<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Application;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Shared\Application\UseCases\CheckHealth;
use Tests\Support\Shared\FakeDatabaseStatusChecker;

final class CheckHealthTest extends TestCase
{
    /** The report is healthy when the database answers, at the given moment. */
    public function test_reports_healthy_when_the_database_answers(): void
    {
        $now = new DateTimeImmutable('2026-10-12T14:00:00-06:00');

        $report = new CheckHealth(new FakeDatabaseStatusChecker(true))->execute($now);

        $this->assertTrue($report->isHealthy());
        $this->assertTrue($report->databaseAvailable);
        $this->assertSame($now, $report->checkedAt);
    }

    /** The report is degraded when the database does not answer. */
    public function test_reports_degraded_when_the_database_does_not_answer(): void
    {
        $report = new CheckHealth(new FakeDatabaseStatusChecker(false))
            ->execute(new DateTimeImmutable);

        $this->assertFalse($report->isHealthy());
        $this->assertFalse($report->databaseAvailable);
    }
}
