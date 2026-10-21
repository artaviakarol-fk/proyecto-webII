<?php

declare(strict_types=1);

namespace Src\Negocios\OpeningHours\Infrastructure\Persistence\Repositories;

use Src\Negocios\OpeningHours\Domain\Contracts\OpeningHoursRepository;
use Src\Negocios\OpeningHours\Domain\ValueObjects\TimeOfDay;
use Src\Negocios\OpeningHours\Domain\ValueObjects\TimeSlot;
use Src\Negocios\OpeningHours\Domain\ValueObjects\Weekday;
use Src\Negocios\OpeningHours\Domain\ValueObjects\WeeklySchedule;
use Src\Negocios\OpeningHours\Infrastructure\Persistence\Models\OpeningHourModel;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class EloquentOpeningHoursRepository implements OpeningHoursRepository
{
    /** Find the weekly schedule of a negocio; without rows the negocio is closed every day. */
    public function find(NegocioId $negocio): WeeklySchedule
    {
        $byDay = [];

        $rows = OpeningHourModel::query()
            ->where('negocio_id', $negocio->value)
            ->orderBy('dia_semana')
            ->orderBy('hora_apertura')
            ->get();

        foreach ($rows as $row) {
            $byDay[$row->dia_semana][] = new TimeSlot(
                TimeOfDay::fromString($row->hora_apertura),
                TimeOfDay::fromString($row->hora_cierre),
            );
        }

        return WeeklySchedule::fromSlots($byDay);
    }

    /** Replace the whole weekly schedule of a negocio; the request transaction keeps it all or nothing. */
    public function replace(NegocioId $negocio, WeeklySchedule $schedule): void
    {
        OpeningHourModel::query()->where('negocio_id', $negocio->value)->delete();

        foreach (Weekday::cases() as $day) {
            foreach ($schedule->slotsOn($day) as $slot) {
                OpeningHourModel::query()->create([
                    'negocio_id' => $negocio->value,
                    'dia_semana' => $day->value,
                    'hora_apertura' => $slot->opens->format(),
                    'hora_cierre' => $slot->closes->format(),
                ]);
            }
        }
    }
}
