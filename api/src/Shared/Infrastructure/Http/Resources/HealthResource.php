<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Shared\Application\DTOs\HealthReport;

/**
 * @property-read HealthReport $resource
 */
final class HealthResource extends JsonResource
{
    /** The health document is served without a "data" envelope. */
    public static $wrap = null;

    /**
     * Expose the health report.
     *
     * @return array{status: string, checks: array{app: string, database: string}, checked_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->resource->isHealthy() ? 'ok' : 'degraded',
            'checks' => [
                'app' => 'ok',
                'database' => $this->resource->databaseAvailable ? 'ok' : 'unavailable',
            ],
            'checked_at' => $this->resource->checkedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
