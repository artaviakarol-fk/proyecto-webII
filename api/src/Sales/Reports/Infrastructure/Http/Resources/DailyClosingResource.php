<?php

declare(strict_types=1);

namespace Src\Sales\Reports\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Sales\Reports\Application\DTOs\DailyClosing;

/**
 * @property-read DailyClosing $resource
 */
final class DailyClosingResource extends JsonResource
{
    /** Wrap the closing of a day. */
    public function __construct(private readonly DailyClosing $closing)
    {
        parent::__construct($closing);
    }

    /**
     * Expose the closing; the totals are colones with two decimals.
     *
     * @return array{fecha: string, cantidad_ventas: int, totales: array{efectivo: float, tarjeta: float, sinpe_movil: float, fiado: float}, total: float}
     */
    public function toArray(Request $request): array
    {
        return [
            'fecha' => $this->closing->date,
            'cantidad_ventas' => $this->closing->salesCount,
            'totales' => [
                'efectivo' => (float) ($this->closing->totals['efectivo'] ?? 0),
                'tarjeta' => (float) ($this->closing->totals['tarjeta'] ?? 0),
                'sinpe_movil' => (float) ($this->closing->totals['sinpe_movil'] ?? 0),
                'fiado' => (float) ($this->closing->totals['fiado'] ?? 0),
            ],
            'total' => (float) $this->closing->total,
        ];
    }
}
