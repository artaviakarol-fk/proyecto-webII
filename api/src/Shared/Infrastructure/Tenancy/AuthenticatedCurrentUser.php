<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Tenancy;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Src\Shared\Domain\Contracts\CurrentUser;

final readonly class AuthenticatedCurrentUser implements CurrentUser
{
    /** Identifier of the account behind the token of the request. */
    public function id(): string
    {
        $id = Auth::id();

        return is_string($id) ? $id : throw new AuthenticationException;
    }
}
