<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Application\UseCases;

use Src\Billing\Payments\Application\Contracts\PaymentGateway;
use Src\Billing\Payments\Application\DTOs\PaymentDetails;
use Src\Billing\Payments\Domain\Contracts\PaymentRepository;
use Src\Billing\Payments\Domain\Entities\Payment;
use Src\Billing\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Billing\Payments\Domain\Exceptions\PaymentNotRefundableException;
use Src\Billing\Payments\Domain\Exceptions\PaymentProviderMismatchException;
use Src\Billing\Payments\Domain\ValueObjects\SaleId;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class RefundPayment
{
    /** Receive the ports of the negocio, the transaction, the payments and the provider. */
    public function __construct(
        private NegocioContext $context,
        private TransactionRunner $transaction,
        private PaymentRepository $payments,
        private PaymentGateway $gateway,
    ) {}

    /**
     * Give back the whole amount of the paid payment of a sale of the negocio. The checks come before the provider is
     * called: the payment must be paid (a second refund is refused) and it must have been charged by the provider that
     * is configured now, because a payment of ONVO cannot be refunded through PayPal. If the provider refuses or does
     * not answer, nothing changes.
     */
    public function execute(string $saleId): PaymentDetails
    {
        $negocioId = $this->context->current();
        $sale = new SaleId($saleId);

        $payment = $this->transaction->run(function () use ($negocioId, $sale): Payment {
            $payment = $this->payments->findLatestForSale($sale, $negocioId) ?? throw PaymentNotFoundException::create();

            if (! $payment->isRefundable() || $payment->reference === null) {
                throw PaymentNotRefundableException::create();
            }

            if ($payment->provider !== $this->gateway->provider()) {
                throw PaymentProviderMismatchException::create($payment->provider, $this->gateway->provider());
            }

            $payment->refund($this->gateway->refund($payment->reference, $payment->amount));
            $this->payments->save($payment);

            return $payment;
        });

        return PaymentDetails::of($payment);
    }
}
