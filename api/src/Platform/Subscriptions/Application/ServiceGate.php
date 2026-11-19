<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Application;

use DateTimeImmutable;
use Src\Platform\Subscriptions\Domain\Contracts\SubscriptionRepository;
use Src\Platform\Subscriptions\Domain\ValueObjects\ServiceStatus;
use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionTerms;
use Src\Shared\Domain\ValueObjects\NegocioId;

/** Tells whether the platform is serving a negocio at a moment, from its rent and from whether it is turned on. */
final readonly class ServiceGate
{
    /** Receive the rents and the terms of the platform. */
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private SubscriptionTerms $terms,
    ) {}

    /** Where the negocio is with the rent; a negocio without a rent is not served. */
    public function status(NegocioId $negocioId, DateTimeImmutable $now): ServiceStatus
    {
        $state = $this->subscriptions->serviceState($negocioId);
        if ($state === null) {
            return ServiceStatus::Suspendido;
        }

        [$subscription, $active] = $state;

        return $subscription->status($now, $this->terms, $active);
    }
}
