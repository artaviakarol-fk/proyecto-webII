<?php

declare(strict_types=1);

namespace Tests\Support\Negocios;

use Src\Negocios\OpeningHours\Domain\Contracts\OpeningHoursRepository;
use Src\Negocios\OpeningHours\Domain\ValueObjects\WeeklySchedule;
use Src\Shared\Domain\ValueObjects\NegocioId;

final class InMemoryOpeningHoursRepository implements OpeningHoursRepository
{
    /** @var array<string, WeeklySchedule> */
    private array $schedules = [];

    /** Find the schedule of the negocio; one never set is closed every day. */
    public function find(NegocioId $negocio): WeeklySchedule
    {
        return $this->schedules[$negocio->value] ?? WeeklySchedule::closed();
    }

    /** Replace the whole schedule of the negocio. */
    public function replace(NegocioId $negocio, WeeklySchedule $schedule): void
    {
        $this->schedules[$negocio->value] = $schedule;
    }
}
