<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Infrastructure\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Platform\Subscriptions\Application\UseCases\HandleSubscriptionWebhook;

final readonly class SubscriptionWebhookController
{
    /**
     * Notification of a provider about a payment of the rent of the platform.
     *
     * It is accepted only when the provider proves it is its own (401 otherwise), and the state is always asked to the
     * provider. A notification that arrives twice adds the months once.
     */
    public function __invoke(Request $request, string $pasarela, HandleSubscriptionWebhook $handle): Response
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[strtolower((string) $name)] = (string) ($values[0] ?? '');
        }

        $handle->execute($pasarela, $request->getContent(), $headers, CarbonImmutable::now()->toDateTimeImmutable());

        return response()->noContent();
    }
}
