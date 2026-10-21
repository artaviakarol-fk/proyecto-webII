<?php

declare(strict_types=1);

namespace Src\Negocios\OpeningHours\Domain\Contracts;

use Src\Negocios\OpeningHours\Domain\ValueObjects\WeeklySchedule;
use Src\Shared\Domain\ValueObjects\NegocioId;

interface OpeningHoursRepository
{
    /** Find the weekly schedule of a negocio; a negocio that never set one is closed every day. */
    public function find(NegocioId $negocio): WeeklySchedule;

    /** Replace the whole weekly schedule of a negocio. */
    public function replace(NegocioId $negocio, WeeklySchedule $schedule): void;
}
