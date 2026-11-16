<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Application\UseCases;

use DateTimeImmutable;
use Src\Billing\Payments\Application\Contracts\PayableSales;
use Src\Billing\Payments\Application\Contracts\PaymentGateway;
use Src\Billing\Payments\Application\DTOs\WebhookOutcome;
use Src\Billing\Payments\Domain\Contracts\PaymentRepository;
use Src\Billing\Payments\Domain\Contracts\WebhookEventRepository;
use Src\Billing\Payments\Domain\Exceptions\InvalidWebhookException;
use Src\Billing\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Billing\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class HandlePaymentWebhook
{
    /** Receive the ports of the negocio, the transaction, the sales, the payments, the processed events and the provider. */
    public function __construct(
        private NegocioContext $context,
        private TransactionRunner $transaction,
        private PayableSales $sales,
        private PaymentRepository $payments,
        private WebhookEventRepository $events,
        private PaymentGateway $gateway,
    ) {}

    /**
     * Take a notification of the provider. It is trusted only when the provider proves it is its own; even then the
     * body is only a hint about which charge changed: the state is always asked to the provider, never read from the
     * notification. An event that was already processed changes nothing. Everything happens in one transaction, so
     * when the provider does not answer the event is not kept as processed and the provider's retry can finish the job.
     *
     * @param  array<string, string>  $headers  Headers of the request, with their names in lowercase.
     *
     * @throws InvalidWebhookException when the notification is not proved to come from the provider
     * @throws PaymentNotFoundException when the charge is not a payment of the negocio
     */
    public function execute(string $payload, array $headers, DateTimeImmutable $now): WebhookOutcome
    {
        if (! $this->gateway->verifyWebhook($payload, $headers)) {
            throw InvalidWebhookException::create();
        }

        $event = $this->gateway->parseWebhook($payload);

        if ($event === null) {
            return WebhookOutcome::Ignored;
        }

        $negocioId = $this->context->current();

        return $this->transaction->run(function () use ($event, $negocioId, $now): WebhookOutcome {
            $payment = $this->payments->findByReference($this->gateway->provider(), $event->reference, $negocioId) ?? throw PaymentNotFoundException::create();

            if (! $this->events->register($negocioId, $this->gateway->provider(), $event, $now)) {
                return WebhookOutcome::Duplicate;
            }

            if ($payment->status === PaymentStatus::Pending && $payment->reference !== null) {
                $payment->apply($this->gateway->status($payment->reference), $now);
                $this->payments->save($payment);

                if ($payment->status === PaymentStatus::Paid) {
                    $this->sales->markPaid($payment->saleId, $payment->method);
                }
            }

            return WebhookOutcome::Processed;
        });
    }
}
