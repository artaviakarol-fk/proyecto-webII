<?php

declare(strict_types=1);

namespace Src\Inventory\Expirations\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Inventory\Expirations\Application\DTOs\ExpiredUnitsSummary;

/**
 * @property-read ExpiredUnitsSummary $resource
 */
final class ExpiredUnitsSummaryResource extends JsonResource
{
    /** Wrap units found expired that are still on the shelf. */
    public function __construct(private readonly ExpiredUnitsSummary $units)
    {
        parent::__construct($units);
    }

    /**
     * Expose the units with the vocabulary of the business.
     *
     * @return array<string, int|string>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->units->id,
            'producto_id' => $this->units->productId,
            'codigo' => $this->units->productCode,
            'producto' => $this->units->productName,
            'cantidad' => $this->units->quantity,
            'motivo' => $this->units->reason,
            'registrada_por' => $this->units->markedBy,
            'registrada_en' => $this->units->markedAt,
        ];
    }
}
