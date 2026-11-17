<?php

declare(strict_types=1);

namespace Src\Inventory\Reservations\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Inventory\Reservations\Application\DTOs\ReservationDetails;

/**
 * @property-read ReservationDetails $resource
 */
final class ReservationResource extends JsonResource
{
    /** Wrap an active reserva. */
    public function __construct(private readonly ReservationDetails $details)
    {
        parent::__construct($details);
    }

    /**
     * Expose the reserva as the cliente sees it; amounts are colones and `expira_en` is when the units are let go.
     *
     * @return array{id: string, producto_id: string, nombre: string, cantidad: int, precio_unitario: float|int, subtotal: float|int, expira_en: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->details->id,
            'producto_id' => $this->details->productId,
            'nombre' => $this->details->productName,
            'cantidad' => $this->details->quantity,
            'precio_unitario' => $this->details->unitPrice->cents / 100,
            'subtotal' => $this->details->subtotal()->cents / 100,
            'expira_en' => $this->details->expiresAt->format(DATE_ATOM),
        ];
    }
}
