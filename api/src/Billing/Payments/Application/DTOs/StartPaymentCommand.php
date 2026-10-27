<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Application\DTOs;

/** The request to pay a sale: how, and the id of the payment method that the client created with the provider. */
final readonly class StartPaymentCommand
{
    /** Hold plain data; the use case validates it. */
    public function __construct(
        public string $saleId,
        public string $method,
        public string $providerMethodId,
    ) {}
}
