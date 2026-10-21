<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\OpenApi;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Illuminate\Support\Str;

/**
 * Documents the errors every endpoint can answer. Scramble infers the validation errors from the Form
 * Requests, but the use cases throw domain exceptions that it cannot follow, so the errors of the API
 * (RFC 9457 problem documents) are added here to each operation.
 */
final class ProblemResponsesTransformer implements DocumentTransformer
{
    public const string SCHEMA = 'Problema';

    private const string MEDIA_TYPE = 'application/problem+json';

    /** Endpoints that need no token. */
    private const array PUBLIC_ENDPOINTS = ['GET /', 'GET /health', 'POST /tokens'];

    /** Endpoints that can answer 409 because a rule of the business refuses the operation. */
    private const array CONFLICTS = [
        'POST /productos',
        'PUT /productos/{producto}',
        'POST /entradas',
        'POST /productos/{producto}/vencidos',
        'POST /ventas',
        'PUT /ventas/{venta}',
        'POST /ventas/{venta}/cobrar',
        'POST /ventas/{venta}/anular',
    ];

    /** Add the problem schema and the errors of each operation of the document. */
    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        $problem = $document->components->addSchema(self::SCHEMA, Schema::fromType($this->problemType()));

        foreach ($document->paths as $path) {
            foreach ($path->operations as $operation) {
                $relative = $this->relativePath($path->path);
                $endpoint = strtoupper($operation->method).' '.$relative;

                foreach ($this->errorsOf($endpoint, $relative) as $status => $description) {
                    if (! $this->answers($operation, $status)) {
                        $operation->addResponse(
                            Response::make($status)->setDescription($description)->setContent(self::MEDIA_TYPE, $problem),
                        );
                    }
                }
            }
        }
    }

    /**
     * The errors an endpoint can answer, by status.
     *
     * @return array<int, string>
     */
    private function errorsOf(string $endpoint, string $path): array
    {
        $errors = [];

        if ($endpoint === 'POST /tokens') {
            $errors[401] = 'Credenciales inválidas: el correo, la contraseña o la cuenta no son correctos.';
            $errors[429] = 'Demasiados intentos de inicio de sesión; hay que esperar antes de volver a intentar.';
        } elseif (! in_array($endpoint, self::PUBLIC_ENDPOINTS, true)) {
            $errors[401] = 'No autenticado: falta el token o no es válido.';
            $errors[403] = 'La cuenta no tiene el permiso (ability) que pide este endpoint.';
        }

        if (str_contains($path, '{')) {
            $errors[404] = 'El recurso no existe o pertenece a otro negocio.';
        }

        if (in_array($endpoint, self::CONFLICTS, true)) {
            $errors[409] = 'Una regla del negocio rechaza la operación (por ejemplo stock insuficiente, código repetido o venta en otro estado). El campo `detail` dice el motivo.';
        }

        return $errors;
    }

    /** The path of an endpoint without the prefix of the API, as the document shows it (`/ventas/{venta}`). */
    private function relativePath(string $path): string
    {
        $prefix = trim((string) config('scramble.api_path'), '/');

        return '/'.trim(Str::after(trim($path, '/'), $prefix), '/');
    }

    /** Tell whether Scramble already documents the status for the operation. */
    private function answers(Operation $operation, int $status): bool
    {
        return array_any(
            $operation->responses ?? [],
            fn (mixed $response): bool => $response instanceof Response && (int) $response->code === $status,
        );
    }

    /** The problem document of RFC 9457 as the API answers it. */
    private function problemType(): ObjectType
    {
        $errors = new ObjectType;
        $errors->additionalProperties((new ArrayType)->setItems(new StringType));

        $problem = new ObjectType;
        $problem->addProperty('type', (new StringType)->setDescription('URL que identifica el tipo de problema.'));
        $problem->addProperty('title', (new StringType)->setDescription('Resumen corto del problema, en español.'));
        $problem->addProperty('status', (new IntegerType)->setDescription('Código HTTP de la respuesta.'));
        $problem->addProperty('detail', (new StringType)->setDescription('Explicación de este caso concreto, en español.'));
        $problem->addProperty('instance', (new StringType)->setDescription('Identificador de la petición (`urn:uuid:`), el mismo del encabezado `X-Request-Id`.'));
        $problem->addProperty('errores', $errors->setDescription('Solo en los 422: los mensajes de cada campo inválido.'));
        $problem->setRequired(['type', 'title', 'status', 'detail', 'instance']);
        $problem->setDescription('Documento de problema (RFC 9457, `application/problem+json`).');

        return $problem;
    }
}
