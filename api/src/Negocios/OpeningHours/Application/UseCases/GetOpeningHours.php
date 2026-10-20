<?php

declare(strict_types=1);

namespace Src\Negocios\OpeningHours\Application\UseCases;

use DateTimeImmutable;
use Src\Negocios\OpeningHours\Application\DTOs\OpeningHoursReport;
use Src\Negocios\OpeningHours\Domain\Contracts\OpeningHoursRepository;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class GetOpeningHours
{
    /** Receive the negocio of the current operation and the repository of the opening hours. */
    public function __construct(
        private NegocioContext $context,
        private OpeningHoursRepository $hours,
    ) {}

    /** Answer the weekly schedule of the negocio of the account and whether it is open at the given moment. */
    public function execute(DateTimeImmutable $now): OpeningHoursReport
    {
        $schedule = $this->hours->find($this->context->current());

        return new OpeningHoursReport($schedule, $schedule->isOpenAt($now));
    }
}
