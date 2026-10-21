<?php

declare(strict_types=1);

namespace Src\Negocios\OpeningHours\Application\UseCases;

use DateTimeImmutable;
use Src\Negocios\OpeningHours\Application\DTOs\OpeningHoursReport;
use Src\Negocios\OpeningHours\Domain\Contracts\OpeningHoursRepository;
use Src\Negocios\OpeningHours\Domain\ValueObjects\WeeklySchedule;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class ReplaceOpeningHours
{
    /** Receive the negocio of the current operation and the repository of the opening hours. */
    public function __construct(
        private NegocioContext $context,
        private OpeningHoursRepository $hours,
    ) {}

    /**
     * Replace the whole weekly schedule of the negocio; a day left out stays closed.
     *
     * @param  array<string, list<array{string, string}>>  $days  Opening and closing time of each slot, by day name.
     */
    public function execute(array $days, DateTimeImmutable $now): OpeningHoursReport
    {
        $schedule = WeeklySchedule::fromTimes($days);
        $this->hours->replace($this->context->current(), $schedule);

        return new OpeningHoursReport($schedule, $schedule->isOpenAt($now));
    }
}
