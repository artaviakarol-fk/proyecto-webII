<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\Contracts;

use DateTimeImmutable;
use Src\Billing\Payments\Domain\ValueObjects\WebhookEvent;
use Src\Shared\Domain\ValueObjects\NegocioId;

interface WebhookEventRepository
{
    /**
     * Keep the event as processed. Answers false, and keeps nothing, when the provider already sent it before:
     * that is how a repeated notification is told apart from a new one.
     */
    public function register(NegocioId $negocioId, string $provider, WebhookEvent $event, DateTimeImmutable $now): bool;
}
