<?php

declare(strict_types=1);

namespace Src\Sales\Purchases\Application\Contracts;

use DateTimeImmutable;
use Src\Billing\Payments\Application\DTOs\PaymentDetails;

interface PurchasePayments
{
    /** Pay the sale with the gateway the cliente chose (the configured one when it chose none). */
    public function start(string $saleId, string $method, string $providerMethodId, ?string $gateway, DateTimeImmutable $now): PaymentDetails;

    /** Show the newest payment of a sale of the cliente of the request; a sale of somebody else is reported as missing. */
    public function show(string $saleId, DateTimeImmutable $now): PaymentDetails;
}
