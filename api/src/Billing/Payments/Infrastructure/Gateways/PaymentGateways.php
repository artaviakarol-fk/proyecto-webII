<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure\Gateways;

use InvalidArgumentException;
use Src\Billing\Payments\Application\Contracts\PaymentGateway;
use Src\Billing\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Billing\Payments\Domain\ValueObjects\PaymentMethod;

/**
 * Builds the gateway of a provider. It is the only place that names the providers: the payments choose one per
 * payment, and when none is chosen the one of PAYMENT_GATEWAY is used, as before.
 */
final class PaymentGateways
{
    /** The providers that can charge a payment. */
    public const array PROVIDERS = [OnvoGateway::PROVIDER, PaypalGateway::PROVIDER];

    /**
     * The gateway of the given provider, or the configured one when none is given.
     *
     * @throws InvalidArgumentException when the provider is not one of the supported
     * @throws GatewayUnavailableException when the provider has no valid credentials in this installation
     */
    public function make(?string $provider = null): PaymentGateway
    {
        $provider ??= (string) config('payments.default');
        if (! in_array($provider, self::PROVIDERS, true)) {
            throw new InvalidArgumentException('PAYMENT_GATEWAY must be one of: onvo, paypal.');
        }

        try {
            return $this->build($provider);
        } catch (InvalidArgumentException) {
            throw GatewayUnavailableException::notConfigured();
        }
    }

    /** Build the gateway of a supported provider with its credentials; it refuses credentials that are not for tests. */
    private function build(string $provider): PaymentGateway
    {
        return match ($provider) {
            OnvoGateway::PROVIDER => new OnvoGateway(
                (string) config('services.onvo.secret'),
                (string) config('services.onvo.base_url'),
                (string) config('services.onvo.webhook_secret'),
            ),
            PaypalGateway::PROVIDER => new PaypalGateway(
                (string) config('services.paypal.client_id'),
                (string) config('services.paypal.client_secret'),
                (string) config('services.paypal.base_url'),
                (float) config('services.paypal.colones_per_dollar'),
                (string) config('services.paypal.webhook_id'),
                (string) config('services.paypal.return_url'),
                (string) config('services.paypal.cancel_url'),
            ),
            default => throw new InvalidArgumentException('PAYMENT_GATEWAY must be one of: onvo, paypal.'),
        };
    }

    /**
     * What the client needs to know to let the buyer choose: the providers, the ways of paying each one offers,
     * whether the client must create the payment method (and with which public key) and whether it has credentials.
     *
     * @return list<array{proveedor: string, nombre: string, metodos: list<string>, requiere_metodo_cliente: bool, llave_publica: string|null, configurada: bool, predeterminada: bool}>
     */
    public function options(): array
    {
        $default = (string) config('payments.default');

        return array_map(function (string $provider) use ($default): array {
            $gateway = $this->usable($provider);

            return [
                'proveedor' => $provider,
                'nombre' => $provider === OnvoGateway::PROVIDER ? 'ONVO Pay' : 'PayPal',
                'metodos' => $gateway === null ? [] : array_map(fn (PaymentMethod $method): string => $method->value, $gateway->supportedMethods()),
                'requiere_metodo_cliente' => $gateway?->requiresClientMethod() ?? false,
                'llave_publica' => $provider === OnvoGateway::PROVIDER ? ((string) config('services.onvo.publishable') ?: null) : null,
                'configurada' => $gateway !== null && ($provider === OnvoGateway::PROVIDER
                    ? (string) config('services.onvo.secret') !== ''
                    : (string) config('services.paypal.client_id') !== '' && (string) config('services.paypal.client_secret') !== ''),
                'predeterminada' => $provider === $default,
            ];
        }, self::PROVIDERS);
    }

    /** The gateway of a provider, or null when its credentials are missing or are not for tests. */
    private function usable(string $provider): ?PaymentGateway
    {
        try {
            return $this->make($provider);
        } catch (GatewayUnavailableException) {
            return null;
        }
    }
}
