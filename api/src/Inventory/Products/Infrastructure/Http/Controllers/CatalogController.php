<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Inventory\Products\Application\DTOs\CatalogItem;
use Src\Inventory\Products\Application\UseCases\ListCatalog;
use Src\Inventory\Products\Application\UseCases\ListProducts;
use Src\Inventory\Products\Infrastructure\Http\Requests\ListProductsRequest;
use Src\Inventory\Products\Infrastructure\Http\Resources\CatalogProductResource;
use Src\Shared\Infrastructure\Http\Resources\PageMeta;

final readonly class CatalogController
{
    /**
     * List what the cliente can buy in its negocio, by pages, optionally searching by name and only of a category.
     *
     * Each product comes with its price and the units available: the ones that can be sold minus the ones that
     * reservas hold right now. The cost of the product is never sent.
     *
     * @response array{data: list<CatalogProductResource>, meta: array{pagina: int, por_pagina: int, total: int, ultima_pagina: int}}
     */
    public function __invoke(ListProductsRequest $request, ListCatalog $listCatalog): JsonResponse
    {
        $page = $listCatalog->execute($request->term(), $request->page(), now()->toDateTimeImmutable(), $request->category());

        return new JsonResponse([
            'data' => array_map(fn (CatalogItem $item): array => new CatalogProductResource($item)->toArray($request), $page->items),
            ...PageMeta::of($page),
        ]);
    }

    /**
     * The categories of the products of the negocio, to show them as filters in the shop.
     *
     * @response array{data: list<string>}
     */
    public function categories(ListProducts $listProducts): JsonResponse
    {
        return new JsonResponse(['data' => $listProducts->categories()]);
    }
}
