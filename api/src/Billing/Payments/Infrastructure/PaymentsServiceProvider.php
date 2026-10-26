<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure;

use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Src\Billing\Payments\Application\Contracts\PayableSales;
use Src\Billing\Payments\Application\Contracts\PaymentGateway;
use Src\Billing\Payments\Domain\Contracts\PaymentRepository;
use Src\Billing\Payments\Infrastructure\Gateways\OnvoGateway;
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
            default => throw new InvalidArgumentException('PAYMENT_GATEWAY must be one of: onvo.'),
        });
    }
}
