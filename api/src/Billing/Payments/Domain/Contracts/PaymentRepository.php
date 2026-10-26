<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\Contracts;

use Src\Billing\Payments\Domain\Entities\Payment;
use Src\Billing\Payments\Domain\Exceptions\PaymentAlreadyStartedException;
use Src\Billing\Payments\Domain\ValueObjects\PaymentId;
use Src\Billing\Payments\Domain\ValueObjects\SaleId;
use Src\Shared\Domain\ValueObjects\NegocioId;

interface PaymentRepository
{
    /** Generate the identifier of a new payment. */
    public function nextId(): PaymentId;

    /** Find a payment of the negocio. */
    public function find(PaymentId $id, NegocioId $negocioId): ?Payment;

    /** The pending or paid payment of a sale of the negocio, if it has one. */
    public function findLiveForSale(SaleId $saleId, NegocioId $negocioId): ?Payment;

    /** The newest payment of a sale of the negocio, whatever its status. */
    public function findLatestForSale(SaleId $saleId, NegocioId $negocioId): ?Payment;

    /**
     * Store a new payment or the changes of one that already exists.
     *
     * @throws PaymentAlreadyStartedException when the sale already has a pending or paid payment
     */
    public function save(Payment $payment): void;
}
