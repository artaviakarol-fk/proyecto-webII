<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Src\Identity\Users\Application\UseCases\IssueToken;
use Src\Identity\Users\Infrastructure\Http\Requests\IssueTokenRequest;
use Src\Identity\Users\Infrastructure\Http\Resources\TokenResource;

final readonly class TokenController
{
    /**
     * Log in with email and password and answer a bearer token.
     *
     * The token goes in the `Authorization: Bearer <token>` header of every later request.
     * A wrong password, an unknown email and an inactive account answer the same 401 problem.
     */
    public function store(IssueTokenRequest $request, IssueToken $issueToken): JsonResponse
    {
        return new TokenResource($issueToken->execute($request->toCommand()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
