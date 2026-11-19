<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Infrastructure\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Platform\Subscriptions\Application\DTOs\ManualPaymentCommand;

final class ManualPaymentRequest extends FormRequest
{
    /**
     * A payment the platform received outside of the system.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /** Meses que se pagaron, de 1 a 12. Por defecto, 1. */
            'meses' => ['sometimes', 'integer', 'between:1,12'],
            /** "efectivo" o "sinpe" (SINPE Móvil al número de la plataforma). */
            'metodo' => ['required', 'string', Rule::in(['efectivo', 'sinpe'])],
            /** Comprobante del SINPE u otra referencia, si la hay. */
            'referencia' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** Build the command of the use case for one negocio. */
    public function toCommand(string $negocioId): ManualPaymentCommand
    {
        return new ManualPaymentCommand(
            $negocioId,
            $this->integer('meses', 1),
            $this->string('metodo')->value(),
            $this->filled('referencia') ? $this->string('referencia')->value() : null,
        );
    }
}
