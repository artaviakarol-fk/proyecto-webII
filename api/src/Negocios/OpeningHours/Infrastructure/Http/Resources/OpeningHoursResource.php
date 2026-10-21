<?php

declare(strict_types=1);

namespace Src\Negocios\OpeningHours\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Negocios\OpeningHours\Application\DTOs\OpeningHoursReport;
use Src\Negocios\OpeningHours\Domain\ValueObjects\TimeSlot;
use Src\Negocios\OpeningHours\Domain\ValueObjects\Weekday;
use Src\Negocios\OpeningHours\Domain\ValueObjects\WeeklySchedule;

/**
 * @property-read OpeningHoursReport $resource
 */
final class OpeningHoursResource extends JsonResource
{
    /** Wrap the opening hours of a negocio. */
    public function __construct(private readonly OpeningHoursReport $report)
    {
        parent::__construct($report);
    }

    /**
     * Expose the seven days, each with its slots (empty when the negocio stays closed), and whether it is open now.
     *
     * @return array{dias: array<string, list<array{abre: string, cierra: string}>>, abierto_ahora: bool, zona_horaria: string}
     */
    public function toArray(Request $request): array
    {
        $days = [];

        foreach (Weekday::cases() as $day) {
            $days[$day->key()] = array_map(
                static fn (TimeSlot $slot): array => ['abre' => $slot->opens->format(), 'cierra' => $slot->closes->format()],
                $this->report->schedule->slotsOn($day),
            );
        }

        return [
            'dias' => $days,
            'abierto_ahora' => $this->report->openNow,
            'zona_horaria' => WeeklySchedule::TIMEZONE,
        ];
    }
}
