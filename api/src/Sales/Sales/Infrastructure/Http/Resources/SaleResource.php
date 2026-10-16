<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Sales\Sales\Application\DTOs\SaleDetails;

/**
 * @property-read SaleDetails $resource
 */
final class SaleResource extends JsonResource
{
    /** Wrap a stored sale. */
    public function __construct(private readonly SaleDetails $sale)
    {
        parent::__construct($sale);
    }

    /**
     * Expose the sale, its cashier and its lines; amounts are colones with two decimals.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->sale->id,
            'estado' => $this->sale->status,
            'metodo_pago' => $this->sale->paymentMethod,
            'cajero' => ['id' => $this->sale->cashierId, 'nombre' => $this->sale->cashierName],
            'cedula_comprador' => $this->sale->buyerIdNumber,
            'creada_en' => $this->sale->createdAt,
            'cobrada_en' => $this->sale->paidAt,
            'anulada_en' => $this->sale->cancelledAt,
            'lineas' => array_map(fn (array $line): array => [
                ...$line,
                'precio_unitario' => (float) $line['precio_unitario'],
                'subtotal' => (float) $line['subtotal'],
            ], $this->sale->lines),
            'total' => (float) $this->sale->total,
            'advertencias' => array_map(fn (array $warning): array => [
                'tipo' => 'unidades_vencidas',
                ...$warning,
                'mensaje' => __('sales.expired_units_warning', ['count' => $warning['unidades_vencidas'], 'product' => $warning['nombre']]),
            ], $this->sale->expiredWarnings),
        ];
    }
}
