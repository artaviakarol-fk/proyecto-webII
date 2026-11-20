<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Application\UseCases;

use DateTimeImmutable;
use Src\Platform\Subscriptions\Application\DTOs\SubscriptionView;
use Src\Platform\Subscriptions\Domain\Contracts\SubscriptionPaymentRepository;
use Src\Platform\Subscriptions\Domain\Contracts\SubscriptionRepository;
use Src\Platform\Subscriptions\Domain\Exceptions\SubscriptionNotFoundException;
use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionTerms;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class GetSubscription
{
    private const int LATEST_PAYMENTS = 10;

    /** Receive the negocio of the request, its rent, its payments and the terms of the platform. */
    public function __construct(
        private NegocioContext $context,
        private SubscriptionRepository $subscriptions,
        private SubscriptionPaymentRepository $payments,
        private SubscriptionTerms $terms,
    ) {}

    /** Where the negocio of the administrator is with the rent, and its last payments. */
    public function execute(DateTimeImmutable $now): SubscriptionView
    {
        $negocioId = $this->context->current();
        $subscription = $this->subscriptions->find($negocioId) ?? throw SubscriptionNotFoundException::create();

        return new SubscriptionView(
            $subscription->status($now, $this->terms, $this->subscriptions->negocioIsActive($negocioId)),
            $subscription->expiresAt,
            $subscription->daysLeft($now),
            $subscription->trial,
            $this->terms->monthlyPrice,
            $this->terms->graceDays,
            $this->payments->latestOf($negocioId, self::LATEST_PAYMENTS),
        );
    }
}
