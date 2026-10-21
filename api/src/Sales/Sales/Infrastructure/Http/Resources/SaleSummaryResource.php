<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Sales\Sales\Application\DTOs\SaleSummary;

/**
 * @property-read SaleSummary $resource
 */
final class SaleSummaryResource extends JsonResource
{
    /** Wrap a sale of the listing. */
    public function __construct(private readonly SaleSummary $sale)
    {
        parent::__construct($sale);
    }

    /**
     * Expose the sale without its lines; the total is colones with two decimals.
     *
     * @return array{id: string, estado: string, metodo_pago: string, cajero: array{id: string, nombre: string}, cantidad_lineas: int, total: float, creada_en: string, cobrada_en: string|null, anulada_en: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->sale->id,
            'estado' => $this->sale->status,
            'metodo_pago' => $this->sale->paymentMethod,
            'cajero' => ['id' => $this->sale->cashierId, 'nombre' => $this->sale->cashierName],
            'cantidad_lineas' => $this->sale->lineCount,
            'total' => (float) $this->sale->total,
            'creada_en' => $this->sale->createdAt,
            'cobrada_en' => $this->sale->paidAt,
            'anulada_en' => $this->sale->cancelledAt,
        ];
    }
}
