<?php

declare(strict_types=1);

namespace Src\Platform\Tenants\Application\DTOs;

use DateTimeImmutable;
use Src\Platform\Subscriptions\Domain\Entities\Subscription;
use Src\Platform\Subscriptions\Domain\ValueObjects\ServiceStatus;
use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionTerms;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class TenantSummary
{
    /**
     * What the platform knows of a negocio: its name, whether it is turned on, when it was opened, its administrators
     * and its rent. The data of the negocio itself (sales, customers, fiado, stock) is never part of it.
     *
     * @param  list<TenantAdministrator>  $administrators
     */
    public function __construct(
        public string $id,
        public string $name,
        public bool $active,
        public DateTimeImmutable $openedAt,
        public array $administrators,
        public ?DateTimeImmutable $paidUntil = null,
        public bool $trial = false,
        public ?ServiceStatus $status = null,
        public ?int $daysLeft = null,
    ) {}

    /** The same summary with where the negocio is with its rent at a moment. */
    public function withService(DateTimeImmutable $now, SubscriptionTerms $terms): self
    {
        if ($this->paidUntil === null) {
            return new self($this->id, $this->name, $this->active, $this->openedAt, $this->administrators, null, false, ServiceStatus::Suspendido, null);
        }

        $subscription = Subscription::reconstitute(new NegocioId($this->id), $this->paidUntil, $this->trial);

        return new self(
            $this->id,
            $this->name,
            $this->active,
            $this->openedAt,
            $this->administrators,
            $this->paidUntil,
            $this->trial,
            $subscription->status($now, $terms, $this->active),
            $subscription->daysLeft($now),
        );
    }
}
