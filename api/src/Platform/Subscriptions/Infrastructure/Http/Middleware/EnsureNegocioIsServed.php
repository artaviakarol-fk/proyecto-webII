<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Infrastructure\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Platform\Subscriptions\Application\ServiceGate;
use Src\Platform\Subscriptions\Domain\Exceptions\NegocioSuspendedException;
use Src\Shared\Domain\ValueObjects\NegocioId;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops the use of a negocio whose service is suspended. Its staff and its clientes are refused; its administrators
 * can still read everything and pay the rent, but not change anything until they pay.
 */
final readonly class EnsureNegocioIsServed
{
    /** What any account can still do: see who it is, change its password and log out. */
    private const array ALWAYS_ALLOWED = [
        'identity.user.show',
        'identity.password.update',
        'identity.tokens.destroy-current',
        'identity.tokens.destroy-all',
    ];

    /** What the administrators of a suspended negocio can still change: the rent. */
    private const array ADMINISTRATION_ALLOWED = ['platform.subscription.*'];

    /** Receive the gate of the platform. */
    public function __construct(private ServiceGate $gate) {}

    /** Let the request through when the negocio is served, or when it is something a suspended negocio may still do. */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('sanctum')->user();
        if (! $user instanceof UserModel || $user->negocio_id === null || $request->routeIs(...self::ALWAYS_ALLOWED)) {
            return $next($request);
        }

        if ($this->gate->status(new NegocioId($user->negocio_id), CarbonImmutable::now()->toDateTimeImmutable())->serves()) {
            return $next($request);
        }

        if (! $user->tokenCan('administrar')) {
            throw NegocioSuspendedException::create();
        }

        if ($request->isMethodSafe() || $request->routeIs(...self::ADMINISTRATION_ALLOWED)) {
            return $next($request);
        }

        throw NegocioSuspendedException::readOnly();
    }
}
