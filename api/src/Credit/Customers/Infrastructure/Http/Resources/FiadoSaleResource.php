<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Credit\Customers\Application\DTOs\FiadoSale;

/**
 * @property-read FiadoSale $resource
 */
final class FiadoSaleResource extends JsonResource
{
    /** Wrap the sale that went on fiado. */
    public function __construct(private readonly FiadoSale $sale)
    {
        parent::__construct($sale);
    }

    /**
     * The sale, its total and the customer with what they owe after the charge.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'venta_id' => $this->sale->saleId,
            'metodo_pago' => 'fiado',
            'total' => $this->sale->total->cents / 100,
            'cliente' => new CustomerResource($this->sale->customer)->toArray($request),
        ];
    }
}
