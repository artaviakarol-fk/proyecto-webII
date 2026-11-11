<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Credit\Customers\Domain\Entities\Customer;

/**
 * @property-read Customer $resource
 */
final class CustomerResource extends JsonResource
{
    /** Wrap a customer aggregate. */
    public function __construct(private readonly Customer $customer)
    {
        parent::__construct($customer);
    }

    /**
     * Expose the customer with the vocabulary of the business; amounts are colones with two decimals.
     * limite_fiado is the base limit; limite_efectivo adds the increases in force right now.
     *
     * @return array<string, bool|int|float|string|null>
     */
    public function toArray(Request $request): array
    {
        $now = now()->toDateTimeImmutable();

        return [
            'id' => $this->customer->id->value,
            'nombre' => $this->customer->name->value,
            'telefono' => $this->customer->phone?->value,
            'limite_fiado' => $this->customer->creditLimit->amount->cents / 100,
            'ampliacion_vigente' => $this->customer->increasesAt($now)->cents / 100,
            'limite_efectivo' => $this->customer->effectiveLimit($now)->cents / 100,
            'saldo_actual' => $this->customer->balance->cents / 100,
            'disponible' => $this->customer->availableCredit($now)->cents / 100,
            'activo' => $this->customer->active,
        ];
    }
}
