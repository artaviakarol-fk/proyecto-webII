<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Tenancy;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ScopeRequestToNegocio
{
    /** Receive the transaction that reaches the rows of one negocio. */
    public function __construct(private NegocioTransaction $transaction) {}

    /**
     * Run the request in one transaction scoped to the negocio of the authenticated account.
     *
     * The negocio always comes from the account behind the token: a negocio_id in the body,
     * the query string or the URL is never read, so changing it cannot reach another negocio.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $negocioId = $request->user()?->getAttribute('negocio_id');

        return $this->transaction->run(is_string($negocioId) ? $negocioId : null, fn (): Response => $next($request));
    }
}
