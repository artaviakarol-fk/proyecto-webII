<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\UseCases;

use Src\Credit\Customers\Application\Contracts\CreditPaymentReader;
use Src\Credit\Customers\Application\DTOs\CreditPaymentReceipt;
use Src\Credit\Customers\Domain\Exceptions\CreditPaymentNotFoundException;
use Src\Credit\Customers\Domain\ValueObjects\CreditPaymentId;
use Src\Shared\Domain\Contracts\NegocioContext;

/** The receipt of an abono, to show it or print it again. */
final readonly class GetCreditPaymentReceipt
{
    /** Receive the negocio of the operation and the reader. */
    public function __construct(
        private NegocioContext $context,
        private CreditPaymentReader $reader,
    ) {}

    /** The receipt of an abono of the current negocio. */
    public function execute(string $paymentId): CreditPaymentReceipt
    {
        return $this->reader->find(new CreditPaymentId($paymentId), $this->context->current())
            ?? throw CreditPaymentNotFoundException::create();
    }
}
