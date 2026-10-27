<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\ValueObjects;

use Src\Shared\Domain\ValueObjects\Money;

/** What the gateway needs to charge a payment once: the amount, the way of paying and the token of the provider. */
final readonly class ChargeRequest
{
    /** The token is the id of the payment method that the client created with the provider. */
    public function __construct(
        public PaymentId $paymentId,
        public SaleId $saleId,
        public Money $amount,
        public PaymentMethod $method,
        public string $methodToken,
        public string $description,
    ) {}
}
