<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Events;

use Illuminate\Contracts\Events\Dispatcher;
use Src\Credit\Customers\Application\Contracts\FiadoBalanceNotifier;
use Src\Credit\Customers\Domain\Entities\Customer;

/** Announces the new balance with a Laravel event that waits for the transaction to commit. */
final readonly class LaravelFiadoBalanceNotifier implements FiadoBalanceNotifier
{
    /** Receive the event dispatcher of the framework. */
    public function __construct(private Dispatcher $events) {}

    /** Dispatch SaldoFiadoActualizado with what the customer owes now. */
    public function balanceChanged(Customer $customer): void
    {
        $this->events->dispatch(new SaldoFiadoActualizado(
            $customer->negocioId->value,
            $customer->id->value,
            $customer->balance->toDecimal(),
        ));
    }
}
