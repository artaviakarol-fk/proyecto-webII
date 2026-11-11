<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The customer an open sale is charged to. */
final class SellOnFiadoRequest extends FormRequest
{
    /**
     * Validation rules: the customer is required; whether it belongs to the negocio and is active is checked by the use case.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** Cliente activo del negocio al que se carga la venta. */
            'cliente_id' => ['required', 'uuid'],
        ];
    }

    /** Identifier of the customer. */
    public function customerId(): string
    {
        return $this->string('cliente_id')->lower()->toString();
    }
}
