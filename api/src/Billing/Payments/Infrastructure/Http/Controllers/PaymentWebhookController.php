<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Billing\Payments\Application\UseCases\HandlePaymentWebhook;
use Src\Billing\Payments\Infrastructure\Gateways\PaymentGateways;

final readonly class PaymentWebhookController
{
    /**
     * Receive a payment notification from the provider.
     *
     * No token is used: the provider proves itself with its signature or secret. The address may name the provider
     * (`/webhooks/pagos/{negocio}/paypal`); without it the configured one is used, so the addresses already given to
     * the providers keep working. Answers 200 when the notification was processed, was already processed or is not
     * about a payment; 401 when it is not proved to come from the provider.
     */
    public function __invoke(string $negocio, Request $request, PaymentGateways $gateways, ?string $pasarela = null): JsonResponse
    {
        $headers = array_map(fn (array $values): string => (string) ($values[0] ?? ''), $request->headers->all());

        $handlePaymentWebhook = app(HandlePaymentWebhook::class, ['gateway' => $gateways->make($pasarela)]);
        $outcome = $handlePaymentWebhook->execute($request->getContent(), $headers, now()->toDateTimeImmutable());

        return new JsonResponse(['estado' => $outcome->value]);
    }
}
