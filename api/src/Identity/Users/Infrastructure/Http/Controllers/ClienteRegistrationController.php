<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Src\Identity\Users\Application\UseCases\RegisterCliente;
use Src\Identity\Users\Infrastructure\Http\Requests\RegisterClienteRequest;
use Src\Identity\Users\Infrastructure\Http\Resources\CurrentUserResource;

final readonly class ClienteRegistrationController
{
    /**
     * Register as cliente of a negocio.
     *
     * Public: it needs no token. The account is always created with the `cliente` role, whatever the request
     * sends, and it answers the new account without a token; the person logs in afterwards with `POST /tokens`.
     * An email that already has an account answers 409, and a negocio that does not exist or is inactive, 404.
     */
    public function __invoke(RegisterClienteRequest $request, RegisterCliente $registerCliente): JsonResponse
    {
        return new CurrentUserResource($registerCliente->execute($request->toCommand()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
