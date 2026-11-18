<?php

declare(strict_types=1);

namespace Src\Inventory\Expirations\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Inventory\Expirations\Application\UseCases\ListExpiredUnits;
use Src\Inventory\Expirations\Application\UseCases\MarkUnitsAsExpired;
use Src\Inventory\Expirations\Infrastructure\Http\Requests\MarkExpiredRequest;
use Src\Inventory\Expirations\Infrastructure\Http\Resources\ExpiredUnitsResource;
use Src\Inventory\Expirations\Infrastructure\Http\Resources\ExpiredUnitsSummaryResource;

final readonly class ExpiredUnitsController
{
    /** Mark units of a product as expired; they leave the sellable stock. */
    public function store(string $producto, MarkExpiredRequest $request, MarkUnitsAsExpired $markUnits): JsonResponse
    {
        return new ExpiredUnitsResource($markUnits->execute($request->toCommand($producto)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /** List the units of the negocio found expired that are still on the shelf, the most recent first. */
    public function index(ListExpiredUnits $listUnits): AnonymousResourceCollection
    {
        return ExpiredUnitsSummaryResource::collection($listUnits->execute());
    }
}
