<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Domain\Entities;

use DateInterval;
use DateTimeImmutable;
use Src\Platform\Subscriptions\Domain\ValueObjects\ServiceStatus;
use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionTerms;
use Src\Shared\Domain\ValueObjects\NegocioId;

/**
 * The rent of a negocio: the date until which its service is paid, and whether that date comes from the free trial.
 * The status is never stored: it is worked out from the date at each moment, so nothing has to run at midnight.
 */
final readonly class Subscription
{
    private const int SECONDS_PER_DAY = 86_400;

    private function __construct(
        public NegocioId $negocioId,
        public DateTimeImmutable $expiresAt,
        public bool $trial,
    ) {}

    /** A new negocio starts with the free trial of the platform. */
    public static function startTrial(NegocioId $negocioId, DateTimeImmutable $now, SubscriptionTerms $terms): self
    {
        return new self($negocioId, $now->add(new DateInterval("P{$terms->trialDays}D")), true);
    }

    /** Rebuild the rent from its stored state. */
    public static function reconstitute(NegocioId $negocioId, DateTimeImmutable $expiresAt, bool $trial): self
    {
        return new self($negocioId, $expiresAt, $trial);
    }

    /**
     * Where the negocio is at a moment. A negocio that the platform turned off is suspended whatever its date.
     */
    public function status(DateTimeImmutable $now, SubscriptionTerms $terms, bool $negocioActive = true): ServiceStatus
    {
        if (! $negocioActive) {
            return ServiceStatus::Suspendido;
        }

        $warningFrom = $this->expiresAt->sub(new DateInterval("P{$terms->warningDays}D"));
        $graceUntil = $this->expiresAt->add(new DateInterval("P{$terms->graceDays}D"));

        return match (true) {
            $now < $warningFrom => ServiceStatus::Activo,
            $now < $this->expiresAt => ServiceStatus::PorVencer,
            $now < $graceUntil => ServiceStatus::EnGracia,
            default => ServiceStatus::Suspendido,
        };
    }

    /** Whole days until the date; negative once it passed. A day that already started counts as one. */
    public function daysLeft(DateTimeImmutable $now): int
    {
        $seconds = $this->expiresAt->getTimestamp() - $now->getTimestamp();

        return (int) ($seconds >= 0 ? ceil($seconds / self::SECONDS_PER_DAY) : -ceil(-$seconds / self::SECONDS_PER_DAY));
    }

    /**
     * The rent after a payment of some months. A payment made before the date adds the months after it, so paying
     * early loses nothing; one made after the date counts from the moment it is paid. The trial is over.
     */
    public function extend(int $months, DateTimeImmutable $paidAt): self
    {
        $from = $this->expiresAt > $paidAt ? $this->expiresAt : $paidAt;

        return new self($this->negocioId, $from->add(new DateInterval("P{$months}M")), false);
    }
}
