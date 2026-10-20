<?php

declare(strict_types=1);

namespace Src\Negocios\OpeningHours\Application\DTOs;

use Src\Negocios\OpeningHours\Domain\ValueObjects\WeeklySchedule;

final readonly class OpeningHoursReport
{
    /** Hold the weekly schedule of the negocio and whether it is open at the moment of the question. */
    public function __construct(
        public WeeklySchedule $schedule,
        public bool $openNow,
    ) {}
}
