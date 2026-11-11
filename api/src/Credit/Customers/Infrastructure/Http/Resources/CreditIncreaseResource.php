<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Credit\Customers\Application\DTOs\CreditIncreaseSummary;

/**
 * @property-read CreditIncreaseSummary $resource
 */
final class CreditIncreaseResource extends JsonResource
{
    /** Wrap an increase. */
    public function __construct(private readonly CreditIncreaseSummary $increase)
    {
        parent::__construct($increase);
    }

    /**
     * The audit of the increase: who gave it, when, how much, why, until when and whether it was reverted.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->increase->id,
            'cliente' => ['id' => $this->increase->customerId, 'nombre' => $this->increase->customerName],
            'monto' => (float) $this->increase->amount,
            'motivo' => $this->increase->reason,
            'otorgada_por' => ['id' => $this->increase->grantedById, 'nombre' => $this->increase->grantedByName],
            'otorgada_at' => $this->increase->grantedAt,
            'vigente_hasta' => $this->increase->validUntil,
            'vigente' => $this->increase->active,
            'revertida_at' => $this->increase->revertedAt,
            'revertida_por' => $this->increase->revertedById === null ? null : ['id' => $this->increase->revertedById, 'nombre' => $this->increase->revertedByName],
        ];
    }
}
