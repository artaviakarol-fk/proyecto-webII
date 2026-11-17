<?php

declare(strict_types=1);

namespace Src\Sales\Purchases\Application\UseCases;

use DateTimeImmutable;
use Src\Billing\Payments\Application\DTOs\PaymentDetails;
use Src\Sales\Purchases\Application\Contracts\PurchasePayments;

final readonly class ShowPurchasePayment
{
    /** Receive the payments of the purchases. */
    public function __construct(private PurchasePayments $payments) {}

    /** Show how the payment of a purchase of the cliente stands; while it is pending the gateway is asked what happened. */
    public function execute(string $saleId, DateTimeImmutable $now): PaymentDetails
    {
        return $this->payments->show($saleId, $now);
    }
}
