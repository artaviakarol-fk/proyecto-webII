<?php

declare(strict_types=1);

namespace Src\Inventory\Restock\Infrastructure\Http\Controllers;

use Src\Inventory\Restock\Application\UseCases\GetRestockReport;
use Src\Inventory\Restock\Infrastructure\Http\Requests\RestockRequest;
use Src\Inventory\Restock\Infrastructure\Http\Resources\RestockReportResource;

final readonly class RestockController
{
    /**
     * Show which products to restock and how many units to order.
     *
     * The warehouse and the administration see it. For each product it gives the units sold in the last days, the
     * average per day, the days the stock lasts at that pace, how urgent it is (agotado, critico, bajo or ok) and the
     * units to order to cover 14 days. The most urgent come first, and the best sellers come apart. It only reads.
     */
    public function __invoke(RestockRequest $request, GetRestockReport $getRestockReport): RestockReportResource
    {
        return new RestockReportResource($getRestockReport->execute($request->days(), $request->asOf()));
    }
}
