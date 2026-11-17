<?php

declare(strict_types=1);

namespace Src\Sales\Purchases\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Sales\Purchases\Application\Contracts\CartReservations;
use Src\Sales\Purchases\Application\Contracts\PurchasePayments;
use Src\Sales\Purchases\Infrastructure\Billing\BillingPurchasePayments;
use Src\Sales\Purchases\Infrastructure\Inventory\InventoryCartReservations;

final class PurchasesServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        CartReservations::class => InventoryCartReservations::class,
        PurchasePayments::class => BillingPurchasePayments::class,
    ];
}
