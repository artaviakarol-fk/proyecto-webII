<?php

declare(strict_types=1);

namespace Src\Negocios\OpeningHours\Domain\ValueObjects;

use DateTimeImmutable;
use DateTimeZone;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class WeeklySchedule
{
    public const int MAX_SLOTS_PER_DAY = 3;

    /** Time zone the hours are written in: the negocios of the system are in Costa Rica. */
    public const string TIMEZONE = 'America/Costa_Rica';

    /**
     * Hold the weekly opening hours; a day without slots is a day the negocio stays closed.
     *
     * @param  array<int, list<TimeSlot>>  $slots  Slots of each day, by the number of the day, sorted by opening time.
     */
    private function __construct(private array $slots) {}

    /** A schedule with every day closed. */
    public static function closed(): self
    {
        return new self([]);
    }

    /**
     * Build the schedule from the slots of each day, by the number of the day.
     *
     * @param  array<int, list<TimeSlot>>  $byDay
     */
    public static function fromSlots(array $byDay): self
    {
        $slots = [];

        foreach (Weekday::cases() as $day) {
            $daySlots = $byDay[$day->value] ?? [];

            if (count($daySlots) > self::MAX_SLOTS_PER_DAY) {
                throw new InvalidValueException('negocios.schedule_too_many_slots', ['max' => self::MAX_SLOTS_PER_DAY]);
            }

            usort($daySlots, static fn (TimeSlot $a, TimeSlot $b): int => $a->opens->minutes <=> $b->opens->minutes);

            for ($i = 1; $i < count($daySlots); $i++) {
                if ($daySlots[$i - 1]->overlaps($daySlots[$i])) {
                    throw new InvalidValueException('negocios.schedule_slots_overlap');
                }
            }

            if ($daySlots !== []) {
                $slots[$day->value] = $daySlots;
            }
        }

        return new self($slots);
    }

    /**
     * Build the schedule from times written as HH:MM, by the name of the day in the API.
     *
     * @param  array<string, list<array{string, string}>>  $days  Each slot is the opening and the closing time.
     */
    public static function fromTimes(array $days): self
    {
        $byDay = [];

        foreach ($days as $key => $times) {
            $byDay[Weekday::fromKey((string) $key)->value] = array_map(
                static fn (array $time): TimeSlot => new TimeSlot(TimeOfDay::fromString($time[0]), TimeOfDay::fromString($time[1])),
                $times,
            );
        }

        return self::fromSlots($byDay);
    }

    /**
     * The slots of a day, earliest first.
     *
     * @return list<TimeSlot>
     */
    public function slotsOn(Weekday $day): array
    {
        return $this->slots[$day->value] ?? [];
    }

    /** Whether the negocio is open at a moment, read in the time zone of the negocio. */
    public function isOpenAt(DateTimeImmutable $moment): bool
    {
        $local = $moment->setTimezone(new DateTimeZone(self::TIMEZONE));
        $time = TimeOfDay::fromMoment($local);

        foreach ($this->slotsOn(Weekday::of($local)) as $slot) {
            if ($slot->contains($time)) {
                return true;
            }
        }

        return false;
    }
}
