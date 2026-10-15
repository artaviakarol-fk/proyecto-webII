<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Tenancy;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class AuthenticatedNegocioContext implements NegocioContext
{
    /** Resolve the negocio of the authenticated account; it never comes from the request body or the URL. */
    public function current(): NegocioId
    {
        $user = Auth::user() ?? throw new AuthenticationException;
        $negocioId = $user->getAttribute('negocio_id');

        return new NegocioId(is_string($negocioId) ? $negocioId : throw new AuthorizationException);
    }
}
