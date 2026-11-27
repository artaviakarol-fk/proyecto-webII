<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Sales\Sales\Domain\Entities\Sale;

/** How the cashier collects: in cash by default, or by card (datáfono) or SINPE Móvil with the reference of the payment. */
final class PaySaleRequest extends FormRequest
{
    /** The route middleware decides who may collect. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules: a card or SINPE payment needs its reference, and cash takes none.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            /** Forma de cobro en caja: efectivo (por defecto), tarjeta (datáfono) o sinpe_movil (transferencia recibida). */
            'metodo_pago' => ['sometimes', 'string', Rule::in([Sale::CASH, ...Sale::REFERENCED_METHODS])],
            /** Número de autorización del datáfono o de comprobante de la transferencia SINPE. Obligatorio con tarjeta y SINPE Móvil. */
            'referencia' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('metodo_pago'), Sale::REFERENCED_METHODS, true)),
                Rule::prohibitedIf(fn (): bool => ! in_array($this->input('metodo_pago'), Sale::REFERENCED_METHODS, true)),
                'string',
                'min:1',
                'max:'.Sale::MAX_REFERENCE_LENGTH,
            ],
        ];
    }

    /** The way of collecting asked for; cash when none. */
    public function method(): string
    {
        return $this->string('metodo_pago', Sale::CASH)->toString();
    }

    /** The reference of a card or SINPE payment, or none. */
    public function reference(): ?string
    {
        return $this->filled('referencia') ? trim($this->string('referencia')->toString()) : null;
    }
}
