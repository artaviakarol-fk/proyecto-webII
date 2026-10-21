<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Http\Controllers;

use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Src\Identity\Users\Application\UseCases\GetCurrentUser;
use Src\Identity\Users\Application\UseCases\IssueToken;
use Src\Identity\Users\Application\UseCases\RevokeAllTokens;
use Src\Identity\Users\Application\UseCases\RevokeCurrentToken;
use Src\Identity\Users\Infrastructure\Http\Requests\IssueTokenRequest;
use Src\Identity\Users\Infrastructure\Http\Resources\CurrentUserResource;
use Src\Identity\Users\Infrastructure\Http\Resources\TokenResource;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;

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

    /** Show the account behind the token: name, email, role, what the role allows and its negocio. */
    public function show(GetCurrentUser $getCurrentUser): CurrentUserResource
    {
        return new CurrentUserResource($getCurrentUser->execute());
    }

    /** Log out on this device: revoke the token the request was made with; from then on it answers 401. */
    public function destroyCurrent(#[CurrentUser] UserModel $user, RevokeCurrentToken $revokeCurrentToken): Response
    {
        $revokeCurrentToken->execute($user->id, (string) $user->currentAccessToken()->getKey());

        return response()->noContent();
    }

    /** Log out on every device: revoke every token of the account, including the one of the request. */
    public function destroyAll(#[CurrentUser] UserModel $user, RevokeAllTokens $revokeAllTokens): Response
    {
        $revokeAllTokens->execute($user->id);

        return response()->noContent();
    }
}
