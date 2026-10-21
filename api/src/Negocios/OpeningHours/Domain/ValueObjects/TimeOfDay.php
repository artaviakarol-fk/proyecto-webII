<?php

declare(strict_types=1);

namespace Src\Negocios\OpeningHours\Domain\ValueObjects;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class TimeOfDay
{
    private const string PATTERN = '/^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/';

    /** Hold a time of the day as the minutes elapsed since midnight. */
    private function __construct(public int $minutes) {}

    /** Read a time written as HH:MM (a trailing :SS is accepted and ignored). */
    public static function fromString(string $value): self
    {
        if (preg_match(self::PATTERN, $value, $parts) !== 1) {
            throw new InvalidValueException('negocios.schedule_time_invalid');
        }

        return new self(((int) $parts[1]) * 60 + (int) $parts[2]);
    }

    /** The time of the day of a moment, in the time zone of that moment. */
    public static function fromMoment(DateTimeImmutable $moment): self
    {
        return new self(((int) $moment->format('G')) * 60 + (int) $moment->format('i'));
    }

    /** Whether this time comes strictly before the other one. */
    public function isBefore(self $other): bool
    {
        return $this->minutes < $other->minutes;
    }

    /** The time written as HH:MM. */
    public function format(): string
    {
        return sprintf('%02d:%02d', intdiv($this->minutes, 60), $this->minutes % 60);
    }
}
