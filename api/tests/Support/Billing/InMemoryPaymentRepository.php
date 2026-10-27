<?php

declare(strict_types=1);

namespace Tests\Support\Billing;

use Src\Billing\Payments\Domain\Contracts\PaymentRepository;
use Src\Billing\Payments\Domain\Entities\Payment;
use Src\Billing\Payments\Domain\Exceptions\PaymentAlreadyStartedException;
use Src\Billing\Payments\Domain\ValueObjects\PaymentId;
use Src\Billing\Payments\Domain\ValueObjects\SaleId;
use Src\Shared\Domain\ValueObjects\NegocioId;

final class InMemoryPaymentRepository implements PaymentRepository
{
    /** @var array<string, Payment> */
    private array $payments = [];

    private int $counter = 0;

    /** A valid, time-ordered looking identifier for each new payment. */
    public function nextId(): PaymentId
    {
        return new PaymentId(sprintf('0192f0c4-7b1e-7c3a-9f1d-%012d', ++$this->counter));
    }

    /** Find a payment of the negocio. */
    public function find(PaymentId $id, NegocioId $negocioId): ?Payment
    {
        $payment = $this->payments[$id->value] ?? null;

        return $payment !== null && $payment->negocioId->equals($negocioId) ? $payment : null;
    }

    /** The pending or paid payment of a sale of the negocio. */
    public function findLiveForSale(SaleId $saleId, NegocioId $negocioId): ?Payment
    {
        return array_find(
            $this->payments,
            fn (Payment $payment): bool => $payment->negocioId->equals($negocioId) && $payment->saleId->equals($saleId) && $payment->status->isLive(),
        );
    }

    /** The newest payment of a sale of the negocio. */
    public function findLatestForSale(SaleId $saleId, NegocioId $negocioId): ?Payment
    {
        $payments = array_values(array_filter(
            $this->payments,
            fn (Payment $payment): bool => $payment->negocioId->equals($negocioId) && $payment->saleId->equals($saleId),
        ));

        return $payments === [] ? null : $payments[count($payments) - 1];
    }

    /** Keep the payment; a sale cannot have two live payments, as the index of the database ensures. */
    public function save(Payment $payment): void
    {
        $other = $this->findLiveForSale($payment->saleId, $payment->negocioId);

        if ($payment->status->isLive() && $other !== null && ! $other->id->equals($payment->id)) {
            throw PaymentAlreadyStartedException::create();
        }

        $this->payments[$payment->id->value] = $payment;
    }

    /** How many payments are kept. */
    public function count(): int
    {
        return count($this->payments);
    }
}
