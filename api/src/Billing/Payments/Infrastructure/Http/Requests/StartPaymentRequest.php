<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Billing\Payments\Application\DTOs\StartPaymentCommand;
use Src\Billing\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Billing\Payments\Infrastructure\Gateways\OnvoGateway;
use Stringable;

/**
 * How the sale is paid and the id of the payment method that the client created with the provider.
 * Card data never reaches this API: the client sends it to the provider with its publishable key. PayPal needs
 * no payment method from the client: the buyer chooses how to pay on the page of PayPal.
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
            'metodo_proveedor_id' => [Rule::requiredIf(fn (): bool => config('payments.default') === OnvoGateway::PROVIDER), 'nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    /** Map the validated data to the use case input. */
    public function toCommand(string $saleId): StartPaymentCommand
    {
        return new StartPaymentCommand($saleId, (string) $this->input('metodo_pago'), (string) $this->input('metodo_proveedor_id', ''));
    }
}
