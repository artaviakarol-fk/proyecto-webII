<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Billing\Payments\Application\Contracts\PaymentGateway;
use Src\Billing\Payments\Application\DTOs\StartPaymentCommand;
use Src\Billing\Payments\Domain\ValueObjects\PaymentMethod;
use Stringable;

/**
 * How the sale is paid and the id of the payment method that the client created with the provider.
 * Card data never reaches this API: the client sends it to the provider with its publishable key. Whether the client must send it
 * depends on the configured provider, which the gateway declares.
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
            'metodo_proveedor_id' => [Rule::requiredIf(fn (): bool => app(PaymentGateway::class)->requiresClientMethod()), 'nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    /** Map the validated data to the use case input. */
    public function toCommand(string $saleId): StartPaymentCommand
    {
        return new StartPaymentCommand($saleId, (string) $this->input('metodo_pago'), (string) $this->input('metodo_proveedor_id', ''));
    }
}
