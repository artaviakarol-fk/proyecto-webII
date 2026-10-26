<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Application\UseCases;

use DateTimeImmutable;
use Src\Billing\Payments\Application\Contracts\PaymentGateway;
use Src\Billing\Payments\Application\DTOs\PaymentDetails;
use Src\Billing\Payments\Domain\Contracts\PaymentRepository;
use Src\Billing\Payments\Domain\Entities\Payment;
use Src\Billing\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Billing\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Billing\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Billing\Payments\Domain\ValueObjects\SaleId;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class GetPayment
{
    /** Receive the ports of the negocio, the transaction, the payments and the provider. */
    public function __construct(
        private NegocioContext $context,
        private TransactionRunner $transaction,
        private PaymentRepository $payments,
        private PaymentGateway $gateway,
    ) {}

    /**
     * Answer the newest payment of a sale of the negocio. While it is pending, ask the provider what happened
     * and keep the answer; if the provider does not answer, the stored state is shown. A sale of another negocio
     * is reported as without payment.
     */
    public function execute(string $saleId, DateTimeImmutable $now): PaymentDetails
    {
        $negocioId = $this->context->current();
        $sale = new SaleId($saleId);

        $payment = $this->transaction->run(function () use ($negocioId, $sale, $now): Payment {
            $payment = $this->payments->findLatestForSale($sale, $negocioId) ?? throw PaymentNotFoundException::create();

            if ($payment->status === PaymentStatus::Pending && $payment->reference !== null) {
                try {
                    $payment->apply($this->gateway->status($payment->reference), $now);
                    $this->payments->save($payment);
                } catch (GatewayUnavailableException) {
                    // The provider is down: the payment keeps the state it already has.
                }
            }

            return $payment;
        });

        return PaymentDetails::of($payment);
    }
}
