<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Identity\Users\Domain\Contracts\NegocioServiceAccess;
use Src\Platform\Subscriptions\Application\Contracts\SubscriptionCharger;
use Src\Platform\Subscriptions\Domain\Contracts\SubscriptionPaymentRepository;
use Src\Platform\Subscriptions\Domain\Contracts\SubscriptionRepository;
use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionTerms;
use Src\Platform\Subscriptions\Infrastructure\Access\GateNegocioServiceAccess;
use Src\Platform\Subscriptions\Infrastructure\Billing\GatewaySubscriptionCharger;
use Src\Platform\Subscriptions\Infrastructure\Persistence\DatabaseSubscriptionPaymentRepository;
use Src\Platform\Subscriptions\Infrastructure\Persistence\DatabaseSubscriptionRepository;
use Src\Shared\Domain\ValueObjects\Money;

final class SubscriptionsServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        SubscriptionRepository::class => DatabaseSubscriptionRepository::class,
        SubscriptionPaymentRepository::class => DatabaseSubscriptionPaymentRepository::class,
        SubscriptionCharger::class => GatewaySubscriptionCharger::class,
        NegocioServiceAccess::class => GateNegocioServiceAccess::class,
    ];

    /** The terms of the rent come from config/plataforma.php. */
    public function register(): void
    {
        $this->app->bind(SubscriptionTerms::class, fn (): SubscriptionTerms => new SubscriptionTerms(
            new Money(((int) config('plataforma.precio_mensual')) * 100),
            (int) config('plataforma.dias_prueba'),
            (int) config('plataforma.dias_aviso'),
            (int) config('plataforma.dias_gracia'),
        ));
    }
}
