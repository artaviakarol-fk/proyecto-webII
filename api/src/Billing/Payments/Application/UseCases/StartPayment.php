<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Application\UseCases;

use DateTimeImmutable;
use Src\Billing\Payments\Application\Contracts\PayableSales;
use Src\Billing\Payments\Application\Contracts\PaymentGateway;
use Src\Billing\Payments\Application\DTOs\PaymentDetails;
use Src\Billing\Payments\Application\DTOs\StartPaymentCommand;
use Src\Billing\Payments\Domain\Contracts\PaymentRepository;
use Src\Billing\Payments\Domain\Entities\Payment;
use Src\Billing\Payments\Domain\Exceptions\PaymentAlreadyStartedException;
use Src\Billing\Payments\Domain\Exceptions\PaymentMethodNotSupportedException;
use Src\Billing\Payments\Domain\Exceptions\SaleNotFoundException;
use Src\Billing\Payments\Domain\Exceptions\SaleNotPayableException;
use Src\Billing\Payments\Domain\ValueObjects\ChargeRequest;
use Src\Billing\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Billing\Payments\Domain\ValueObjects\SaleId;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class StartPayment
{
    /** Receive the ports of the negocio, the transaction, the sales, the payments and the provider. */
    public function __construct(
        private NegocioContext $context,
        private TransactionRunner $transaction,
        private PayableSales $sales,
        private PaymentRepository $payments,
        private PaymentGateway $gateway,
    ) {}

    /**
     * Start the payment of an open sale: it is stored as pending, the provider is asked to charge it and the
     * answer is stored. If the provider refuses or does not answer, nothing is stored. A way of paying that the
     * configured provider does not offer (SINPE Móvil with PayPal) is refused before anything is stored or sent.
     */
    public function execute(StartPaymentCommand $command, DateTimeImmutable $now): PaymentDetails
    {
        $negocioId = $this->context->current();
        $saleId = new SaleId($command->saleId);
        $method = PaymentMethod::tryFrom($command->method) ?? throw new InvalidValueException('payments.method_invalid');

        if (! in_array($method, $this->gateway->supportedMethods(), true)) {
            throw PaymentMethodNotSupportedException::create($method->value, $this->gateway->provider());
        }

        $payment = $this->transaction->run(function () use ($command, $negocioId, $saleId, $method, $now): Payment {
            $sale = $this->sales->find($saleId) ?? throw SaleNotFoundException::create();

            if (! $sale->open) {
                throw SaleNotPayableException::create();
            }

            if ($this->payments->findLiveForSale($saleId, $negocioId) !== null) {
                throw PaymentAlreadyStartedException::create();
            }

            $payment = Payment::start($this->payments->nextId(), $negocioId, $saleId, $this->gateway->provider(), $method, $sale->total, $now);
            $this->payments->save($payment);

            $payment->apply($this->gateway->charge(new ChargeRequest(
                $payment->id,
                $saleId,
                $sale->total,
                $method,
                $command->providerMethodId,
                'Venta '.$saleId->value,
            )), $now);
            $this->payments->save($payment);

            return $payment;
        });

        return PaymentDetails::of($payment);
    }
}
