<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Src\Identity\Users\Domain\Exceptions\PasswordChangeRequiredException;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsurePasswordIsChanged
{
    /** What an account with a temporary password may still do: see who it is, change the password and log out. */
    private const array ALLOWED_ROUTES = [
        'identity.user.show',
        'identity.password.update',
        'identity.tokens.destroy-current',
        'identity.tokens.destroy-all',
    ];

    /**
     * Refuse every other route while the account keeps the temporary password somebody else chose for it.
     *
     * The token is read with the same guard that the routes use, so the account is looked up only once per request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('sanctum')->user();

        if ($user instanceof UserModel && $user->debe_cambiar_contrasena && ! $request->routeIs(...self::ALLOWED_ROUTES)) {
            throw PasswordChangeRequiredException::create();
        }

        return $next($request);
    }
}
