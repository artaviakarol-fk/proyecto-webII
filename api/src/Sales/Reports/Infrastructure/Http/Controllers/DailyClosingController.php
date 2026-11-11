<?php

declare(strict_types=1);

namespace Src\Sales\Reports\Infrastructure\Http\Controllers;

use Src\Sales\Reports\Application\UseCases\GetDailyClosing;
use Src\Sales\Reports\Infrastructure\Http\Requests\DailyClosingRequest;
use Src\Sales\Reports\Infrastructure\Http\Resources\DailyClosingResource;

final readonly class DailyClosingController
{
    /**
     * Show the income of a day by way of paying.
     *
     * Only the administration sees it. It adds the paid sales of the day (efectivo, tarjeta, sinpe_movil and fiado) and
     * counts them; it has no cash count and no missing or extra money.
     */
    public function __invoke(DailyClosingRequest $request, GetDailyClosing $getDailyClosing): DailyClosingResource
    {
        return new DailyClosingResource($getDailyClosing->execute($request->day()));
    }
}
