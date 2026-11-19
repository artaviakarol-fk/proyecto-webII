<?php

declare(strict_types=1);

namespace Src\Identity\StaffRoles\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Identity\StaffRoles\Application\DTOs\StaffRoleView;
use Src\Identity\StaffRoles\Application\UseCases\CreateStaffRole;
use Src\Identity\StaffRoles\Application\UseCases\DeleteStaffRole;
use Src\Identity\StaffRoles\Application\UseCases\ListStaffRoles;
use Src\Identity\StaffRoles\Application\UseCases\UpdateStaffRole;
use Src\Identity\StaffRoles\Infrastructure\Http\Requests\SaveStaffRoleRequest;
use Src\Identity\StaffRoles\Infrastructure\Http\Resources\StaffRoleResource;

final readonly class StaffRoleController
{
    /**
     * The roles the administrator can give to its staff.
     *
     * First the four default roles (administrador, cajero, bodeguero, supervisor), which every negocio has and nobody
     * can change, and then the roles the negocio created.
     */
    public function index(Request $request, ListStaffRoles $list): JsonResponse
    {
        return new JsonResponse([
            'data' => array_map(fn (StaffRoleView $role): array => new StaffRoleResource($role)->toArray($request), $list->execute()),
        ]);
    }

    /**
     * Create a role of the negocio.
     *
     * A name and at least one of the permissions that exist: "administrar", "caja" and "inventario". A name already
     * used in the negocio answers 409.
     */
    public function store(SaveStaffRoleRequest $request, CreateStaffRole $create): JsonResponse
    {
        return new StaffRoleResource($create->execute($request->toCommand()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Rename a role of the negocio or change its permissions.
     *
     * Whoever has the role logs in again to receive the new permissions. Taking the permission to administrate away
     * from the only administrators of the negocio answers 409.
     */
    public function update(SaveStaffRoleRequest $request, string $rol, UpdateStaffRole $update): StaffRoleResource
    {
        return new StaffRoleResource($update->execute($rol, $request->toCommand()));
    }

    /** Delete a role of the negocio that nobody has; one that somebody still has answers 409. */
    public function destroy(string $rol, DeleteStaffRole $delete): Response
    {
        $delete->execute($rol);

        return response()->noContent();
    }
}
