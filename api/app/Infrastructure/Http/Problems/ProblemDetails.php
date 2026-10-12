<?php

namespace App\Infrastructure\Http\Problems;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Convierte errores en respuestas RFC 9457 (application/problem+json).
 */
final class ProblemDetails
{
    public const CONTENT_TYPE = 'application/problem+json';

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function response(int $status, string $title, ?string $detail = null, ?string $instance = null, array $extra = []): JsonResponse
    {
        $body = array_filter([
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => $instance,
        ], fn ($value) => $value !== null);

        return new JsonResponse(
            array_merge($body, $extra),
            $status,
            ['Content-Type' => self::CONTENT_TYPE],
        );
    }

    public static function fromThrowable(Throwable $e, Request $request): JsonResponse
    {
        $instance = '/'.ltrim($request->path(), '/');

        return match (true) {
            $e instanceof ValidationException => self::response(
                422,
                'Los datos enviados no son válidos.',
                $e->getMessage(),
                $instance,
                ['errors' => $e->errors()],
            ),
            $e instanceof AuthenticationException => self::response(401, 'No autenticado.', null, $instance),
            $e instanceof AuthorizationException => self::response(403, 'No autorizado.', null, $instance),
            $e instanceof ModelNotFoundException => self::response(404, 'Recurso no encontrado.', null, $instance),
            $e instanceof HttpExceptionInterface => self::response(
                $e->getStatusCode(),
                self::titleFor($e->getStatusCode()),
                $e->getMessage() !== '' ? $e->getMessage() : null,
                $instance,
            ),
            default => self::response(
                500,
                'Error interno del servidor.',
                config('app.debug') ? $e->getMessage() : null,
                $instance,
            ),
        };
    }

    private static function titleFor(int $status): string
    {
        return match ($status) {
            400 => 'Solicitud incorrecta.',
            404 => 'Recurso no encontrado.',
            405 => 'Método no permitido.',
            409 => 'Conflicto.',
            429 => 'Demasiadas solicitudes.',
            503 => 'Servicio no disponible.',
            default => 'Error en la solicitud.',
        };
    }
}
