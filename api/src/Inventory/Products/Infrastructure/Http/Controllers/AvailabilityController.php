<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Inventory\Products\Application\UseCases\GetAvailability;

final readonly class AvailabilityController
{
    /**
     * How many units of a product of the negocio can really be sold right now.
     *
     * @response array{data: array{producto_id: string, codigo: string, nombre: string, stock: int, unidades_vencidas: int, stock_vendible: int, agotado: bool}}
     */
    public function __invoke(string $producto, GetAvailability $getAvailability): JsonResponse
    {
        [$product, $availability] = $getAvailability->execute($producto);

        return new JsonResponse(['data' => [
            'producto_id' => $product->id->value,
            'codigo' => $product->details->code->value,
            'nombre' => $product->details->name->value,
            'stock' => $availability->stock->units,
            'unidades_vencidas' => $availability->expiredUnits,
            'stock_vendible' => $availability->sellable,
            'agotado' => $availability->isSoldOut(),
        ]]);
    }
}
