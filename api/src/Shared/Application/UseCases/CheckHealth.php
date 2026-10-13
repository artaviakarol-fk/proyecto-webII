<?php

declare(strict_types=1);

namespace Src\Shared\Application\UseCases;

use DateTimeImmutable;
use Src\Shared\Application\DTOs\HealthReport;
use Src\Shared\Domain\Contracts\DatabaseStatusChecker;

final readonly class CheckHealth
{
    /** Receive the port that reports the database status. */
    public function __construct(private DatabaseStatusChecker $database) {}

    /** Check the application and its dependencies at the given moment. */
    public function execute(DateTimeImmutable $now): HealthReport
    {
        return new HealthReport(
            databaseAvailable: $this->database->isAvailable(),
            checkedAt: $now,
        );
    }
}
