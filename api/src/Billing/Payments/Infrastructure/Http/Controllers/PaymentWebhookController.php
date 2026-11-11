<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Billing\Payments\Application\UseCases\HandlePaymentWebhook;

final readonly class PaymentWebhookController
{
    /**
     * Receive a notification of the payment provider. No token is used: the provider proves itself with its signature or
     * secret. Answers 200 when the notification was processed, was already processed or is not about a payment; 401 when
     * it is not proved to come from the provider.
     */
    public function __invoke(string $negocio, Request $request, HandlePaymentWebhook $handlePaymentWebhook): JsonResponse
    {
        $headers = array_map(fn (array $values): string => (string) ($values[0] ?? ''), $request->headers->all());

        $outcome = $handlePaymentWebhook->execute($request->getContent(), $headers, now()->toDateTimeImmutable());

        return new JsonResponse(['estado' => $outcome->value]);
    }
}
