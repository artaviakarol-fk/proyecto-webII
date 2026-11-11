<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Src\Credit\Customers\Application\DTOs\CreditIncreaseSummary;
use Src\Credit\Customers\Application\UseCases\GrantCreditIncrease;
use Src\Credit\Customers\Application\UseCases\ListCreditIncreases;
use Src\Credit\Customers\Application\UseCases\RevertCreditIncrease;
use Src\Credit\Customers\Infrastructure\Http\Requests\GrantCreditIncreaseRequest;
use Src\Credit\Customers\Infrastructure\Http\Requests\ListCreditIncreasesRequest;
use Src\Credit\Customers\Infrastructure\Http\Resources\CreditIncreaseResource;
use Src\Credit\Customers\Infrastructure\Http\Resources\CustomerResource;
use Src\Shared\Infrastructure\Http\Resources\PageMeta;

final readonly class CreditIncreaseController
{
    /**
     * The cashier increases the limit of a customer until the end of the day, with a reason. The base
     * limit plus the increases in force can never pass the maximum (₡120 000).
     *
     * @response 201 array{data: array{ampliacion: CreditIncreaseResource, cliente: CustomerResource}}
     */
    public function store(GrantCreditIncreaseRequest $request, string $cliente, GrantCreditIncrease $grant): JsonResponse
    {
        $granted = $grant->execute($cliente, $request->amount(), $request->reason(), now()->toDateTimeImmutable());

        return new JsonResponse(['data' => [
            'ampliacion' => new CreditIncreaseResource($granted->increase)->toArray($request),
            'cliente' => new CustomerResource($granted->customer)->toArray($request),
        ]], Response::HTTP_CREATED);
    }

    /**
     * The administration reviews the increases, the newest first.
     *
     * @response array{data: list<CreditIncreaseResource>, meta: array{pagina: int, por_pagina: int, total: int, ultima_pagina: int}}
     */
    public function index(ListCreditIncreasesRequest $request, ListCreditIncreases $list): JsonResponse
    {
        $page = $list->execute($request->filter(), $request->page());

        return new JsonResponse([
            'data' => array_map(fn (CreditIncreaseSummary $increase): array => new CreditIncreaseResource($increase)->toArray($request), $page->items),
            ...PageMeta::of($page),
        ]);
    }

    /** The administration reverts an increase that is still in force; the record stays. */
    public function revert(string $ampliacion, RevertCreditIncrease $revert): CreditIncreaseResource
    {
        return new CreditIncreaseResource($revert->execute($ampliacion, now()->toDateTimeImmutable()));
    }
}
