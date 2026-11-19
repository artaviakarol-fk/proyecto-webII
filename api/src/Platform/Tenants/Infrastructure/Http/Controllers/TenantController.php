<?php

declare(strict_types=1);

namespace Src\Platform\Tenants\Infrastructure\Http\Controllers;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Src\Platform\Tenants\Application\DTOs\TenantSummary;
use Src\Platform\Tenants\Application\UseCases\GetTenant;
use Src\Platform\Tenants\Application\UseCases\ListTenants;
use Src\Platform\Tenants\Application\UseCases\RegisterNegocio;
use Src\Platform\Tenants\Application\UseCases\SetNegocioActive;
use Src\Platform\Tenants\Infrastructure\Http\Requests\ListTenantsRequest;
use Src\Platform\Tenants\Infrastructure\Http\Requests\RegisterNegocioRequest;
use Src\Platform\Tenants\Infrastructure\Http\Requests\UpdateTenantRequest;
use Src\Platform\Tenants\Infrastructure\Http\Resources\TenantResource;
use Src\Shared\Infrastructure\Http\Resources\PageMeta;

final readonly class TenantController
{
    /**
     * The negocios of the platform, the newest first, with their administrators.
     *
     * Only a superadmin reaches it. It shows the name, the state, who manages each negocio and where it is with the rent,
     * never its data.
     */
    public function index(ListTenantsRequest $request, ListTenants $list): JsonResponse
    {
        $page = $list->execute($request->term(), $request->page(), self::now());

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
        return new TenantResource($register->execute($request->toCommand(), self::now()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /** One negocio of the platform with its administrators and its rent. */
    public function show(string $negocio, GetTenant $get): TenantResource
    {
        return new TenantResource($get->execute($negocio, self::now()));
    }

    /**
     * Turn a negocio off ("inactivo") or on again ("activo").
     *
     * A negocio turned off is suspended whatever date it paid until: its staff and its clientes cannot use it and its
     * administrators can only see and pay. None of its data is deleted.
     */
    public function update(UpdateTenantRequest $request, string $negocio, SetNegocioActive $set): TenantResource
    {
        return new TenantResource($set->execute($negocio, $request->active(), self::now()));
    }

    /** The current moment. */
    private static function now(): DateTimeImmutable
    {
        return CarbonImmutable::now()->toDateTimeImmutable();
    }
}
