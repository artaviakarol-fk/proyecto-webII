<?php

namespace App\Application\Health;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Caso de uso: revisar el estado de la aplicación y de sus dependencias.
 */
final readonly class CheckHealth
{
    public function __construct(
        private DatabaseStatusChecker $database,
    ) {}

    public function __invoke(): HealthReport
    {
        return new HealthReport(
            databaseAvailable: $this->database->isAvailable(),
            checkedAt: (new DateTimeImmutable)->format(DateTimeInterface::ATOM),
        );
    }
}
