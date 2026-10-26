<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure;

use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Src\Billing\Payments\Application\Contracts\PayableSales;
use Src\Billing\Payments\Application\Contracts\PaymentGateway;
use Src\Billing\Payments\Domain\Contracts\PaymentRepository;
use Src\Billing\Payments\Infrastructure\Gateways\OnvoGateway;
use Src\Billing\Payments\Infrastructure\Gateways\PaypalGateway;
use Src\Billing\Payments\Infrastructure\Persistence\Repositories\EloquentPaymentRepository;
use Src\Billing\Payments\Infrastructure\Sales\SalesPayableSales;

final class PaymentsServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        PaymentRepository::class => EloquentPaymentRepository::class,
        PayableSales::class => SalesPayableSales::class,
    ];

    /** Bind the gateway port to the provider named by PAYMENT_GATEWAY: the only place that names the providers. */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, fn (): PaymentGateway => match ((string) config('payments.default')) {
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
        });
    }
}
