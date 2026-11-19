<?php

declare(strict_types=1);

namespace Src\Platform\Tenants\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Src\Platform\Tenants\Application\DTOs\TenantSummary;
use Src\Platform\Tenants\Application\UseCases\GetTenant;
use Src\Platform\Tenants\Application\UseCases\ListTenants;
use Src\Platform\Tenants\Application\UseCases\RegisterNegocio;
use Src\Platform\Tenants\Infrastructure\Http\Requests\ListTenantsRequest;
use Src\Platform\Tenants\Infrastructure\Http\Requests\RegisterNegocioRequest;
use Src\Platform\Tenants\Infrastructure\Http\Resources\TenantResource;
use Src\Shared\Infrastructure\Http\Resources\PageMeta;

final readonly class TenantController
{
    /**
     * The negocios of the platform, the newest first, with their administrators.
     *
     * Only a superadmin reaches it. It shows the name, the state and who manages each negocio, never its data.
     */
    public function index(ListTenantsRequest $request, ListTenants $list): JsonResponse
    {
        $page = $list->execute($request->term(), $request->page());

        return new JsonResponse([
            'data' => array_map(fn (TenantSummary $tenant): array => new TenantResource($tenant)->toArray($request), $page->items),
            ...PageMeta::of($page),
        ]);
    }

    /**
     * Open a negocio with its first administrator.
     *
     * The administrator logs in with the temporary password and must change it before anything else; then it opens
     * the accounts of its own staff. An email that already has an account answers 409.
     */
    public function store(RegisterNegocioRequest $request, RegisterNegocio $register): JsonResponse
    {
        return new TenantResource($register->execute($request->toCommand()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /** One negocio of the platform with its administrators. */
    public function show(string $negocio, GetTenant $get): TenantResource
    {
        return new TenantResource($get->execute($negocio));
    }
}
