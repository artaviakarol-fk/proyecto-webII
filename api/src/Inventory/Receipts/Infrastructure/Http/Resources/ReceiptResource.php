<?php

declare(strict_types=1);

namespace Src\Inventory\Receipts\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Inventory\Receipts\Application\DTOs\ReceiptDetails;

/**
 * @property-read ReceiptDetails $resource
 */
final class ReceiptResource extends JsonResource
{
    /** Wrap a stored receipt. */
    public function __construct(private readonly ReceiptDetails $receipt)
    {
        parent::__construct($receipt);
    }

    /**
     * Expose the receipt, who registered it and when; amounts are colones with two decimals.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->receipt->id,
            'proveedor' => $this->receipt->supplier,
            'fecha' => $this->receipt->date,
            'registrada_por' => ['id' => $this->receipt->receivedById, 'nombre' => $this->receipt->receivedByName],
            'registrada_en' => $this->receipt->registeredAt,
            'lineas' => array_map(fn (array $line): array => [
                ...$line,
                'costo_unitario' => (float) $line['costo_unitario'],
                'subtotal' => (float) $line['subtotal'],
                'costo_anterior' => $line['costo_anterior'] === null ? null : (float) $line['costo_anterior'],
                'diferencia_costo' => $line['diferencia_costo'] === null ? null : (float) $line['diferencia_costo'],
                'precio_venta' => (float) $line['precio_venta'],
            ], $this->receipt->lines),
            'total' => (float) $this->receipt->total,
        ];
    }
}
