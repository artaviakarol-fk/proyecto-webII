<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\ValueObjects\NegocioId;
use Src\Shared\Infrastructure\Tenancy\FixedNegocioContext;
use Src\Shared\Infrastructure\Tenancy\NegocioTransaction;
use Symfony\Component\HttpFoundation\Response;

final readonly class ScopeWebhookToNegocio
{
    /** Receive the transaction that reaches the rows of one negocio. */
    public function __construct(private NegocioTransaction $transaction) {}

    /**
     * Run the notification in one transaction scoped to the negocio of the URL that the provider calls. There is no
     * account behind a notification, so the URL names the negocio; it is only a place to deliver to, the notification
     * is trusted after its signature is checked. A negocio that does not exist reaches no row.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $negocio = (string) $request->route('negocio');

        app()->instance(NegocioContext::class, new FixedNegocioContext(new NegocioId($negocio)));

        try {
            return $this->transaction->run($negocio, fn (): Response => $next($request));
        } finally {
            app()->forgetInstance(NegocioContext::class);
        }
    }
}
