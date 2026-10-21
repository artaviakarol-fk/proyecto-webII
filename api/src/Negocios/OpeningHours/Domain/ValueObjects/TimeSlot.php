<?php

declare(strict_types=1);

namespace Src\Negocios\OpeningHours\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class TimeSlot
{
    /** Hold one stretch of a day in which the negocio is open; it closes after it opens. */
    public function __construct(public TimeOfDay $opens, public TimeOfDay $closes)
    {
        if (! $opens->isBefore($closes)) {
            throw new InvalidValueException('negocios.schedule_slot_invalid');
        }
    }

    /** Whether the time falls inside the slot: it counts at the opening time and no longer at the closing time. */
    public function contains(TimeOfDay $time): bool
    {
        return ! $time->isBefore($this->opens) && $time->isBefore($this->closes);
    }

    /** Whether the two slots share any minute; slots that only touch do not overlap. */
    public function overlaps(self $other): bool
    {
        return $this->opens->isBefore($other->closes) && $other->opens->isBefore($this->closes);
    }
}
