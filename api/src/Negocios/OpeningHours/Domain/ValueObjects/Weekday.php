<?php

declare(strict_types=1);

namespace Src\Negocios\OpeningHours\Domain\ValueObjects;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\InvalidValueException;

enum Weekday: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    /** Name of the day in the API, in Spanish and without accents. */
    public function key(): string
    {
        return match ($this) {
            self::Monday => 'lunes',
            self::Tuesday => 'martes',
            self::Wednesday => 'miercoles',
            self::Thursday => 'jueves',
            self::Friday => 'viernes',
            self::Saturday => 'sabado',
            self::Sunday => 'domingo',
        };
    }

    /** Find the day an API name refers to. */
    public static function fromKey(string $key): self
    {
        foreach (self::cases() as $day) {
            if ($day->key() === $key) {
                return $day;
            }
        }

        throw new InvalidValueException('negocios.schedule_day_invalid');
    }

    /** The day of the week a moment falls on, in the time zone of that moment. */
    public static function of(DateTimeImmutable $moment): self
    {
        return self::from((int) $moment->format('N'));
    }
}
