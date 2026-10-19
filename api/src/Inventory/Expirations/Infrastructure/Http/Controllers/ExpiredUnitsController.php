<?php

declare(strict_types=1);

namespace Src\Inventory\Expirations\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Src\Inventory\Expirations\Application\UseCases\MarkUnitsAsExpired;
use Src\Inventory\Expirations\Infrastructure\Http\Requests\MarkExpiredRequest;
use Src\Inventory\Expirations\Infrastructure\Http\Resources\ExpiredUnitsResource;

final readonly class ExpiredUnitsController
{
    /** Mark units of a product as expired; they leave the sellable stock. */
    public function store(string $producto, MarkExpiredRequest $request, MarkUnitsAsExpired $markUnits): JsonResponse
    {
        return new ExpiredUnitsResource($markUnits->execute($request->toCommand($producto)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
