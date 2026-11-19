<?php

declare(strict_types=1);

namespace Src\Identity\Staff\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Identity\Staff\Application\UseCases\ChangeStaffAccess;
use Src\Identity\Staff\Application\UseCases\ListStaff;
use Src\Identity\Staff\Application\UseCases\OpenStaffAccount;
use Src\Identity\Staff\Application\UseCases\ResetStaffPassword;
use Src\Identity\Staff\Infrastructure\Http\Requests\ChangeStaffAccessRequest;
use Src\Identity\Staff\Infrastructure\Http\Requests\OpenStaffAccountRequest;
use Src\Identity\Staff\Infrastructure\Http\Requests\ResetStaffPasswordRequest;
use Src\Identity\Staff\Infrastructure\Http\Resources\StaffResource;
use Src\Identity\Users\Domain\Entities\User;

final readonly class StaffController
{
    /** The staff of the negocio, active or not, ordered by name. The clientes are not staff and are not listed. */
    public function index(Request $request, ListStaff $list): JsonResponse
    {
        return new JsonResponse([
            'data' => array_map(fn (User $user): array => new StaffResource($user)->toArray($request), $list->execute()),
        ]);
    }

    /**
     * Open an account of the staff, including another administrator.
     *
     * The person logs in with the temporary password and must change it before anything else. An email that already
     * has an account answers 409.
     */
    public function store(OpenStaffAccountRequest $request, OpenStaffAccount $open): JsonResponse
    {
        return new StaffResource($open->execute($request->toCommand()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Change the role of an account of the staff, or turn it off or on.
     *
     * The sessions of the account are closed, so it logs in again with what it can do now. An administrator cannot
     * change its own access, and the negocio always keeps at least one active administrator (409).
     */
    public function update(ChangeStaffAccessRequest $request, string $usuario, ChangeStaffAccess $change): StaffResource
    {
        return new StaffResource($change->execute($usuario, $request->toCommand()));
    }

    /** Give a temporary password to somebody of the staff who forgot theirs; its sessions are closed. */
    public function resetPassword(ResetStaffPasswordRequest $request, string $usuario, ResetStaffPassword $reset): Response
    {
        $reset->execute($usuario, $request->temporaryPassword());

        return response()->noContent();
    }
}
