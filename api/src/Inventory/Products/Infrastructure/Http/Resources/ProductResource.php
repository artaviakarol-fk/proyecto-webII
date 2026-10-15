<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Inventory\Products\Domain\Entities\Product;

/**
 * @property-read Product $resource
 */
final class ProductResource extends JsonResource
{
    /** Wrap a product aggregate. */
    public function __construct(private readonly Product $product)
    {
        parent::__construct($product);
    }

    /**
     * Expose the product with the vocabulary of the business; amounts are colones with two decimals.
     *
     * @return array<string, bool|int|float|string|null>
     */
    public function toArray(Request $request): array
    {
        $details = $this->product->details;

        return [
            'id' => $this->product->id->value,
            'codigo' => $details->code->value,
            'nombre' => $details->name->value,
            'categoria' => $details->category?->value,
            'precio_venta' => $details->salePrice->cents / 100,
            'costo_actual' => $details->currentCost->cents / 100,
            'stock' => $details->stock->units,
            'es_perecedero' => $details->perishable,
            'es_licor' => $details->liquor,
            'foto' => $this->product->photoPath,
            'agotado' => $this->product->isSoldOut(),
        ];
    }
}
