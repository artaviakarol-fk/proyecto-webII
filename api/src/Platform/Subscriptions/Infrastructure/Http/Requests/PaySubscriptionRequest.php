<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Infrastructure\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Billing\Payments\Infrastructure\Gateways\PaymentGateways;
use Src\Platform\Subscriptions\Application\DTOs\PaySubscriptionCommand;

final class PaySubscriptionRequest extends FormRequest
{
    /**
     * How many months and how to pay them.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /** Meses que se pagan, de 1 a 12. Por defecto, 1. */
            'meses' => ['sometimes', 'integer', 'between:1,12'],
            /** "onvo" o "paypal". */
            'pasarela' => ['required', 'string', Rule::in(PaymentGateways::PROVIDERS)],
            /** "tarjeta" o "sinpe_movil" (SINPE solo con ONVO). */
            'metodo_pago' => ['required', 'string', Rule::in(['tarjeta', 'sinpe_movil'])],
            /** Id del método de pago que el cliente creó en ONVO con la llave pública. PayPal no lo usa. */
            'metodo_proveedor_id' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    /** Build the command of the use case from the validated body. */
    public function toCommand(): PaySubscriptionCommand
    {
        return new PaySubscriptionCommand(
            $this->integer('meses', 1),
            $this->string('pasarela')->value(),
            $this->string('metodo_pago')->value(),
            $this->filled('metodo_proveedor_id') ? $this->string('metodo_proveedor_id')->value() : null,
        );
    }
}
