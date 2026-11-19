<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Domain\Contracts;

use Src\Platform\Subscriptions\Domain\Entities\SubscriptionPayment;
use Src\Shared\Domain\ValueObjects\NegocioId;

interface SubscriptionPaymentRepository
{
    /** Answer the identifier of the next payment. */
    public function nextId(): string;

    /** A payment of the negocio, locked until the transaction ends. */
    public function findForUpdate(string $id, NegocioId $negocioId): ?SubscriptionPayment;

    /** The payment a provider knows by its reference, locked until the transaction ends. */
    public function findByReferenceForUpdate(string $provider, string $reference): ?SubscriptionPayment;

    /**
     * The last payments of a negocio, the newest first.
     *
     * @return list<SubscriptionPayment>
     */
    public function latestOf(NegocioId $negocioId, int $limit): array;

    /** Store a new payment or its changes. */
    public function save(SubscriptionPayment $payment): void;
}
