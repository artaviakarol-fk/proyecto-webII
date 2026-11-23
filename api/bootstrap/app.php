<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Src\Identity\Users\Infrastructure\Http\Middleware\EnsurePasswordIsChanged;
use Src\Platform\Subscriptions\Infrastructure\Http\Middleware\EnsureNegocioIsServed;
use Src\Shared\Infrastructure\Http\Middleware\AssignRequestId;
use Src\Shared\Infrastructure\Http\Middleware\EncodeJsonAsUnicode;
use Src\Shared\Infrastructure\Http\Problem\ProblemDetailsRenderer;
use Src\Shared\Infrastructure\Tenancy\ScopeRequestToNegocio;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    // Canales privados autorizados con el token de Sanctum, en /api/broadcasting/auth.
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(EncodeJsonAsUnicode::class);
        // En el servidor la API está detrás de Caddy, que recibe el HTTPS. Confiar en sus encabezados
        // X-Forwarded-* hace que las URL que arma la API (fotos, logo, documentación) salgan en https.
        // Es seguro porque la API no publica su puerto: solo Caddy puede hablarle.
        $middleware->trustProxies(at: '*');
        // La API no tiene página de login: sin token responde 401 en lugar de redirigir.
        $middleware->redirectGuestsTo(fn () => null);
        // La contraseña viaja tal cual: los espacios en sus extremos son parte de ella.
        $middleware->trimStrings(except: ['contrasena', 'contrasena_actual', 'contrasena_nueva', 'contrasena_temporal']);
        // Una cuenta con contraseña temporal solo puede cambiarla, ver quién es y cerrar la sesión.
        // Un negocio que no pagó el alquiler: su personal y sus clientes no entran; su administración solo ve y paga.
        $middleware->api(append: [EnsurePasswordIsChanged::class, EnsureNegocioIsServed::class]);

        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'negocio' => ScopeRequestToNegocio::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(new ProblemDetailsRenderer);
    })->create();
