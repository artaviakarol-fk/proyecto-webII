<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * What a customer owes on fiado changed: a sale was charged, a sale was cancelled or a payment came in.
 * It is dispatched only after the transaction commits. The WhatsApp notice (US-505) will listen to it.
 */
final class SaldoFiadoActualizado implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** Carry the negocio, the customer and what they owe now, in colones with two decimals. */
    public function __construct(
        public readonly string $negocioId,
        public readonly string $customerId,
        public readonly string $balance,
    ) {}
}
