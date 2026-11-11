<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Credit\Customers\Domain\Entities\CreditPayment;

/** An abono: how much and how it is paid. */
final class ReceiveCreditPaymentRequest extends FormRequest
{
    /**
     * Validation rules; whether the abono fits what the customer owes is checked by the use case.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** Monto en colones; para cancelar todo, el saldo completo. */
            'monto' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            /** Cómo se paga. Por ahora solo "efectivo"; es el valor por defecto. */
            'metodo_pago' => ['sometimes', 'string', 'max:20'],
        ];
    }

    /** Amount as a decimal string. */
    public function amount(): string
    {
        return (string) $this->input('monto');
    }

    /** Way of paying, cash by default. */
    public function method(): string
    {
        return $this->string('metodo_pago', CreditPayment::CASH)->toString();
    }
}
