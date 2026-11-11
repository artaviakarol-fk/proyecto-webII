<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Credit\Customers\Application\DTOs\CustomerData;
use Src\Credit\Customers\Domain\ValueObjects\CustomerName;
use Src\Credit\Customers\Domain\ValueObjects\PhoneNumber;

/**
 * Data of a customer, used to register it (POST) and to replace it (PUT).
 *
 * The balance is not a field: anything sent as saldo_actual is ignored, because only the
 * movements of fiado change it.
 */
final class CustomerRequest extends FormRequest
{
    /**
     * Validation rules for the data of a customer; the limit is checked against the base limit by the use case.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** Nombre del cliente. */
            'nombre' => ['required', 'string', 'max:'.CustomerName::MAX_LENGTH],
            /** Teléfono, por ejemplo 8888-8888 o +506 8888 8888. */
            'telefono' => ['nullable', 'string', 'max:'.PhoneNumber::MAX_LENGTH, 'regex:'.PhoneNumber::PATTERN],
            /** Límite de fiado en colones, de 0 al límite base (₡90 000). Si no se envía, se usa el límite base. */
            'limite_fiado' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0'],
            /** Si el cliente puede comprar a fiado. Por defecto, true. */
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /** Map the validated payload to the use case input; the negocio and the balance never come from here. */
    public function toData(): CustomerData
    {
        return new CustomerData(
            $this->string('nombre')->toString(),
            $this->filled('telefono') ? $this->string('telefono')->toString() : null,
            $this->has('limite_fiado') ? (string) $this->input('limite_fiado') : null,
            $this->boolean('activo', true),
        );
    }
}
