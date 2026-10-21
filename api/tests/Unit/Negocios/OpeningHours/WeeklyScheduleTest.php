<?php

declare(strict_types=1);

namespace Tests\Unit\Negocios\OpeningHours;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Negocios\OpeningHours\Domain\ValueObjects\TimeOfDay;
use Src\Negocios\OpeningHours\Domain\ValueObjects\TimeSlot;
use Src\Negocios\OpeningHours\Domain\ValueObjects\Weekday;
use Src\Negocios\OpeningHours\Domain\ValueObjects\WeeklySchedule;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final class WeeklyScheduleTest extends TestCase
{
    /** The hours of the Minisúper Teresita: Monday to Friday 7:00-20:00, weekends 7:00-18:00. */
    private function teresita(): WeeklySchedule
    {
        $weekday = [['07:00', '20:00']];
        $weekend = [['07:00', '18:00']];

        return WeeklySchedule::fromTimes([
            'lunes' => $weekday, 'martes' => $weekday, 'miercoles' => $weekday, 'jueves' => $weekday, 'viernes' => $weekday,
            'sabado' => $weekend, 'domingo' => $weekend,
        ]);
    }

    /**
     * The negocio is open from its opening time up to, but not including, its closing time, in Costa Rica time (UTC-6).
     */
    #[DataProvider('moments')]
    public function test_is_open_follows_the_hours_in_costa_rica_time(string $utcMoment, bool $expected): void
    {
        $this->assertSame($expected, $this->teresita()->isOpenAt(new DateTimeImmutable($utcMoment.' UTC')));
    }

    /**
     * Moments in UTC, and whether Teresita is open: 2026-10-20 is a Tuesday and 2026-10-24 a Saturday.
     *
     * @return array<string, array{string, bool}>
     */
    public static function moments(): array
    {
        return [
            'one minute before opening' => ['2026-10-20 12:59:00', false],
            'at opening time' => ['2026-10-20 13:00:00', true],
            'weekday at 19:59' => ['2026-10-21 01:59:00', true],
            'weekday at closing time' => ['2026-10-21 02:00:00', false],
            'saturday at 17:59' => ['2026-10-24 23:59:00', true],
            'saturday at closing time' => ['2026-10-25 00:00:00', false],
            'sunday at noon' => ['2026-10-25 18:00:00', true],
            'late at night' => ['2026-10-20 08:00:00', false],
        ];
    }

    /** A day without slots is a closed day, and a schedule never set is closed every day. */
    public function test_a_day_without_slots_is_closed(): void
    {
        $schedule = WeeklySchedule::fromTimes(['lunes' => [['08:00', '12:00']]]);

        $this->assertFalse($schedule->isOpenAt(new DateTimeImmutable('2026-10-20 18:00:00 UTC')));
        $this->assertSame([], $schedule->slotsOn(Weekday::Tuesday));
        $this->assertFalse(WeeklySchedule::closed()->isOpenAt(new DateTimeImmutable('2026-10-19 18:00:00 UTC')));
    }

    /** A day can have several slots, for example a break at midday; they come back sorted by opening time. */
    public function test_slots_of_a_day_come_back_sorted(): void
    {
        $schedule = WeeklySchedule::fromTimes(['lunes' => [['14:00', '20:00'], ['07:00', '12:00']]]);

        $slots = $schedule->slotsOn(Weekday::Monday);

        $this->assertSame(['07:00', '14:00'], [$slots[0]->opens->format(), $slots[1]->opens->format()]);
    }

    /** The break is closed: 12:00 to 14:00 falls between the two slots. */
    public function test_the_break_is_closed(): void
    {
        $schedule = WeeklySchedule::fromTimes(['lunes' => [['07:00', '12:00'], ['14:00', '20:00']]]);

        $this->assertTrue($schedule->isOpenAt(new DateTimeImmutable('2026-10-19 17:59:00 UTC')));
        $this->assertFalse($schedule->isOpenAt(new DateTimeImmutable('2026-10-19 18:00:00 UTC')));
        $this->assertFalse($schedule->isOpenAt(new DateTimeImmutable('2026-10-19 19:59:00 UTC')));
        $this->assertTrue($schedule->isOpenAt(new DateTimeImmutable('2026-10-19 20:00:00 UTC')));
    }

    /**
     * Schedules that make no sense are rejected with a translation key.
     *
     * @param  array<string, list<array{string, string}>>  $days
     */
    #[DataProvider('invalidSchedules')]
    public function test_invalid_schedules_are_rejected(array $days, string $translationKey): void
    {
        try {
            WeeklySchedule::fromTimes($days);
            $this->fail('The schedule should have been rejected.');
        } catch (InvalidValueException $exception) {
            $this->assertSame($translationKey, $exception->translationKey());
        }
    }

    /**
     * Invalid schedules and the message each one answers.
     *
     * @return array<string, array{array<string, list<array{string, string}>>, string}>
     */
    public static function invalidSchedules(): array
    {
        return [
            'closes before opening' => [['lunes' => [['20:00', '07:00']]], 'negocios.schedule_slot_invalid'],
            'closes when it opens' => [['lunes' => [['07:00', '07:00']]], 'negocios.schedule_slot_invalid'],
            'overlapping slots' => [['lunes' => [['07:00', '12:00'], ['11:00', '20:00']]], 'negocios.schedule_slots_overlap'],
            'too many slots' => [['lunes' => [['06:00', '08:00'], ['09:00', '11:00'], ['12:00', '14:00'], ['15:00', '17:00']]], 'negocios.schedule_too_many_slots'],
            'unknown day' => [['feriado' => [['07:00', '12:00']]], 'negocios.schedule_day_invalid'],
            'hour out of range' => [['lunes' => [['07:00', '24:00']]], 'negocios.schedule_time_invalid'],
            'not a time' => [['lunes' => [['mañana', '20:00']]], 'negocios.schedule_time_invalid'],
        ];
    }

    /** Slots that only touch share no minute, so they are valid. */
    public function test_slots_that_touch_do_not_overlap(): void
    {
        $morning = new TimeSlot(TimeOfDay::fromString('07:00'), TimeOfDay::fromString('12:00'));
        $afternoon = new TimeSlot(TimeOfDay::fromString('12:00'), TimeOfDay::fromString('20:00'));

        $this->assertFalse($morning->overlaps($afternoon));
        $this->assertSame('12:00', $afternoon->opens->format());
    }
}
