<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Billing\Payments\Application\DTOs\StartPaymentCommand;
use Src\Billing\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Billing\Payments\Infrastructure\Gateways\PaymentGateways;
use Stringable;

/**
 * How the sale is paid and the id of the payment method that the client created with the provider.
 * Card data never reaches this API: the client sends it to the provider with its publishable key. Whether the client must send it
 * depends on the provider of the payment (the chosen one or the configured one), which the gateway declares.
 */
final class StartPaymentRequest extends FormRequest
{
    /**
     * Validation rules for the payment.
     *
     * @return array<string, list<Stringable|string>>
     */
    public function rules(): array
    {
        return [
            'metodo_pago' => ['required', 'string', Rule::in(array_column(PaymentMethod::cases(), 'value'))],
            'pasarela' => ['nullable', 'string', Rule::in(PaymentGateways::PROVIDERS)],
            'metodo_proveedor_id' => [Rule::requiredIf(fn (): bool => app(PaymentGateways::class)->make($this->gateway())->requiresClientMethod()), 'nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    /** The provider that the buyer chose for this payment; null when none was sent, so the configured one is used. */
    public function gateway(): ?string
    {
        $chosen = $this->input('pasarela');

        return is_string($chosen) && in_array($chosen, PaymentGateways::PROVIDERS, true) ? $chosen : null;
    }

    /** Map the validated data to the use case input. */
    public function toCommand(string $saleId): StartPaymentCommand
    {
        return new StartPaymentCommand($saleId, (string) $this->input('metodo_pago'), (string) $this->input('metodo_proveedor_id', ''));
    }
}
