<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Domain\Contracts;

use Src\Platform\Subscriptions\Domain\Entities\Subscription;
use Src\Shared\Domain\ValueObjects\NegocioId;

interface SubscriptionRepository
{
    /** The rent of a negocio. */
    public function find(NegocioId $negocioId): ?Subscription;

    /** The rent of a negocio, locked until the transaction ends so two payments never extend it at the same time. */
    public function findForUpdate(NegocioId $negocioId): ?Subscription;

    /** Whether the platform keeps the negocio turned on (a superadmin can turn it off whatever its date). */
    public function negocioIsActive(NegocioId $negocioId): bool;

    /**
     * The rent of a negocio together with whether it is turned on, in one read: it is asked on every request.
     *
     * @return array{Subscription, bool}|null
     */
    public function serviceState(NegocioId $negocioId): ?array;

    /** Store the rent of a negocio. */
    public function save(Subscription $subscription): void;
}
