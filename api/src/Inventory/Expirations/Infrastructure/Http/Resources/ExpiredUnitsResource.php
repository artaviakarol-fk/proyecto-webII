<?php

declare(strict_types=1);

namespace Src\Inventory\Expirations\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Inventory\Expirations\Application\DTOs\MarkedUnits;

/**
 * @property-read MarkedUnits $resource
 */
final class ExpiredUnitsResource extends JsonResource
{
    /** Wrap the units just marked as expired. */
    public function __construct(private readonly MarkedUnits $units)
    {
        parent::__construct($units);
    }

    /**
     * Expose the mark and how the stock of the product stands afterwards.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->units->id,
            'producto_id' => $this->units->productId,
            'cantidad' => $this->units->quantity,
            'motivo' => $this->units->reason,
            'estado' => $this->units->status,
            'registrada_por' => $this->units->markedBy,
            'registrada_en' => $this->units->markedAt,
            'stock' => $this->units->stock,
            'unidades_vencidas' => $this->units->expiredUnits,
            'stock_vendible' => $this->units->sellableStock,
        ];
    }
}
