<?php

namespace App\Application\Health;

final readonly class HealthReport
{
    public function __construct(
        public bool $databaseAvailable,
        public string $checkedAt,
    ) {}

    public function isHealthy(): bool
    {
        return $this->databaseAvailable;
    }

    /**
     * @return array{status: string, checks: array{app: string, database: string}, checked_at: string}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->isHealthy() ? 'ok' : 'degraded',
            'checks' => [
                'app' => 'ok',
                'database' => $this->databaseAvailable ? 'ok' : 'unavailable',
            ],
            'checked_at' => $this->checkedAt,
        ];
    }
}
