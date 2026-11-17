<?php

declare(strict_types=1);

namespace Src\Sales\Purchases\Application\UseCases;

use Src\Billing\Payments\Application\DTOs\PaymentDetails;
use Src\Sales\Purchases\Application\Contracts\CartReservations;
use Src\Sales\Purchases\Application\Contracts\PurchasePayments;
use Src\Sales\Purchases\Application\DTOs\PurchaseCommand;
use Src\Sales\Purchases\Domain\Exceptions\NoReservationsToBuyException;
use Src\Sales\Sales\Application\UseCases\OpenSale;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class BuyReservations
{
    /** Receive the transaction, the reservas of the cliente, the sales and the payments. */
    public function __construct(
        private TransactionRunner $transaction,
        private CartReservations $cart,
        private OpenSale $openSale,
        private PurchasePayments $payments,
    ) {}

    /**
     * Turn what the cliente reserved into a sale and start its payment with the gateway it chose. Everything happens in
     * one transaction: the reservas are taken, the sale takes its units out of the stock and the gateway is asked to
     * charge. If any step fails (not enough stock, liquor without id number, the gateway refuses) nothing is kept and the
     * reservas stay active, so the cliente can try again with another way of paying.
     *
     * @throws NoReservationsToBuyException when the cliente holds no active reserva
     */
    public function execute(PurchaseCommand $command): PaymentDetails
    {
        return $this->transaction->run(function () use ($command): PaymentDetails {
            $lines = $this->cart->claim($command->now);

            if ($lines === []) {
                throw NoReservationsToBuyException::create();
            }

            $sale = $this->openSale->execute($lines, $command->buyerIdNumber);

            return $this->payments->start($sale->id, $command->method, $command->providerMethodId, $command->gateway, $command->now);
        });
    }
}
