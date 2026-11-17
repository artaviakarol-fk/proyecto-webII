<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Inventory\Products\Application\DTOs\CatalogItem;

/**
 * @property-read CatalogItem $resource
 */
final class CatalogProductResource extends JsonResource
{
    /** Wrap a product of the catalog. */
    public function __construct(private readonly CatalogItem $item)
    {
        parent::__construct($item);
    }

    /**
     * Expose only what a cliente needs to buy. The cost, the code and the raw stock are data of the negocio and
     * are left out on purpose; `disponible` is what can be sold and nobody holds in a reserva.
     *
     * @return array{id: string, nombre: string, categoria: string|null, precio_venta: float|int, disponible: int, agotado: bool, es_licor: bool, foto: string|null}
     */
    public function toArray(Request $request): array
    {
        $product = $this->item->product;
        $details = $product->details;

        return [
            'id' => $product->id->value,
            'nombre' => $details->name->value,
            'categoria' => $details->category?->value,
            'precio_venta' => $details->salePrice->cents / 100,
            'disponible' => $this->item->available(),
            'agotado' => $this->item->isSoldOut(),
            'es_licor' => $details->liquor,
            'foto' => $product->photoPath === null ? null : route('inventory.catalog.photo.show', $product->id->value),
        ];
    }
}
