<?php

declare(strict_types=1);

namespace Src\Shared\Application\DTOs;

use DateTimeImmutable;

final readonly class HealthReport
{
    /** Carry the result of a health check. */
    public function __construct(
        public bool $databaseAvailable,
        public DateTimeImmutable $checkedAt,
    ) {}

    /** The application is healthy when every dependency answers. */
    public function isHealthy(): bool
    {
        return $this->databaseAvailable;
    }
}
