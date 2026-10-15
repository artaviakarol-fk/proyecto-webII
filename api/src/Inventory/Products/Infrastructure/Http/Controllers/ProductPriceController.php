<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Inventory\Products\Application\DTOs\PriceHistoryEntry;
use Src\Inventory\Products\Application\UseCases\ChangeSalePrice;
use Src\Inventory\Products\Application\UseCases\GetPriceHistory;
use Src\Inventory\Products\Infrastructure\Http\Requests\ChangeSalePriceRequest;
use Src\Inventory\Products\Infrastructure\Http\Resources\ProductResource;

final readonly class ProductPriceController
{
    /** Accept a new sale price for a product of the negocio. Only the administration. */
    public function update(ChangeSalePriceRequest $request, string $producto, ChangeSalePrice $changePrice): ProductResource
    {
        return new ProductResource($changePrice->execute($producto, $request->salePrice()));
    }

    /** The history of cost and sale price of a product, newest first. */
    public function history(string $producto, GetPriceHistory $getHistory): JsonResponse
    {
        return new JsonResponse(['data' => array_map(fn (PriceHistoryEntry $entry): array => [
            'fecha' => $entry->recordedAt,
            'costo' => (float) $entry->cost,
            'precio_venta' => (float) $entry->salePrice,
            'motivo' => $entry->reason,
            'usuario' => ['id' => $entry->userId, 'nombre' => $entry->userName],
            'referencia_id' => $entry->referenceId,
        ], $getHistory->execute($producto))]);
    }
}
