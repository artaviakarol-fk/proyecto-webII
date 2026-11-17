<?php

declare(strict_types=1);

namespace Src\Sales\Purchases\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Billing\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Billing\Payments\Infrastructure\Gateways\PaymentGateways;
use Src\Sales\Purchases\Application\DTOs\PurchaseCommand;
use Stringable;

/**
 * How the cliente pays what it reserved: the way of paying, the gateway of this payment and, when the gateway asks for
 * it, the payment method created with the provider. Liquor also needs the id number of the buyer.
 */
final class PurchaseRequest extends FormRequest
{
    /**
     * Validation rules for the purchase.
     *
     * @return array<string, list<Stringable|string>>
     */
    public function rules(): array
    {
        return [
            'metodo_pago' => ['required', 'string', Rule::in(array_column(PaymentMethod::cases(), 'value'))],
            'pasarela' => ['required', 'string', Rule::in(PaymentGateways::PROVIDERS)],
            'metodo_proveedor_id' => [Rule::requiredIf(fn (): bool => $this->gateway() !== null && app(PaymentGateways::class)->make($this->gateway())->requiresClientMethod()), 'nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            'cedula_comprador' => ['nullable', 'string', 'max:20', 'regex:/^[0-9\s-]+$/'],
        ];
    }

    /** The gateway the cliente chose, only when it is one of the supported. */
    private function gateway(): ?string
    {
        $chosen = $this->input('pasarela');

        return is_string($chosen) && in_array($chosen, PaymentGateways::PROVIDERS, true) ? $chosen : null;
    }

    /** Map the validated data to the use case input. */
    public function toCommand(): PurchaseCommand
    {
        $idNumber = $this->input('cedula_comprador');

        return new PurchaseCommand(
            (string) $this->input('metodo_pago'),
            (string) $this->input('pasarela'),
            (string) $this->input('metodo_proveedor_id', ''),
            is_string($idNumber) && trim($idNumber) !== '' ? $idNumber : null,
            now()->toDateTimeImmutable(),
        );
    }
}
