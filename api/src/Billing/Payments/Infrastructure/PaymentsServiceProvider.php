<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Billing\Payments\Application\Contracts\PayableSales;
use Src\Billing\Payments\Application\Contracts\PaymentGateway;
use Src\Billing\Payments\Domain\Contracts\PaymentRepository;
use Src\Billing\Payments\Domain\Contracts\WebhookEventRepository;
use Src\Billing\Payments\Infrastructure\Gateways\PaymentGateways;
use Src\Billing\Payments\Infrastructure\Persistence\Repositories\EloquentPaymentRepository;
use Src\Billing\Payments\Infrastructure\Persistence\Repositories\EloquentWebhookEventRepository;
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
        WebhookEventRepository::class => EloquentWebhookEventRepository::class,
        PayableSales::class => SalesPayableSales::class,
    ];

    /** Bind the gateway port to the provider named by PAYMENT_GATEWAY; a payment may still choose another one (PaymentGateways). */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, fn (): PaymentGateway => $this->app->make(PaymentGateways::class)->make());
    }
}
