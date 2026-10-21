<?php

declare(strict_types=1);

namespace Src\Negocios\OpeningHours\Infrastructure\Http\Controllers;

use Src\Negocios\OpeningHours\Application\UseCases\GetOpeningHours;
use Src\Negocios\OpeningHours\Application\UseCases\ReplaceOpeningHours;
use Src\Negocios\OpeningHours\Infrastructure\Http\Requests\ReplaceOpeningHoursRequest;
use Src\Negocios\OpeningHours\Infrastructure\Http\Resources\OpeningHoursResource;

final readonly class OpeningHoursController
{
    /** Show the weekly opening hours of the negocio of the account and whether it is open right now. */
    public function show(GetOpeningHours $getOpeningHours): OpeningHoursResource
    {
        return new OpeningHoursResource($getOpeningHours->execute(now()->toDateTimeImmutable()));
    }

    /** Replace the whole weekly schedule; a day left out stays closed. Only the administration. */
    public function update(ReplaceOpeningHoursRequest $request, ReplaceOpeningHours $replaceOpeningHours): OpeningHoursResource
    {
        return new OpeningHoursResource($replaceOpeningHours->execute($request->days(), now()->toDateTimeImmutable()));
    }
}
