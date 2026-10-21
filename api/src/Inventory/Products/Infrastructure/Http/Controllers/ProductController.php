<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Src\Inventory\Products\Application\UseCases\CreateProduct;
use Src\Inventory\Products\Application\UseCases\DeleteProduct;
use Src\Inventory\Products\Application\UseCases\GetProduct;
use Src\Inventory\Products\Application\UseCases\ListProducts;
use Src\Inventory\Products\Application\UseCases\UpdateProduct;
use Src\Inventory\Products\Domain\Entities\Product;
use Src\Inventory\Products\Infrastructure\Http\Requests\ListProductsRequest;
use Src\Inventory\Products\Infrastructure\Http\Requests\ProductRequest;
use Src\Inventory\Products\Infrastructure\Http\Resources\ProductResource;
use Src\Shared\Infrastructure\Http\Resources\PageMeta;

final readonly class ProductController
{
    /**
     * List the catalog of the negocio by pages, optionally searching by name or code.
     *
     * @response array{data: list<ProductResource>, meta: array{pagina: int, por_pagina: int, total: int, ultima_pagina: int}}
     */
    public function index(ListProductsRequest $request, ListProducts $listProducts): JsonResponse
    {
        $page = $listProducts->execute($request->term(), $request->page());

        return new JsonResponse([
            'data' => array_map(fn (Product $product): array => new ProductResource($product)->toArray($request), $page->items),
            ...PageMeta::of($page),
        ]);
    }

    /** Show one product of the negocio; a product of another negocio answers 404. */
    public function show(string $producto, GetProduct $getProduct): ProductResource
    {
        return new ProductResource($getProduct->execute($producto));
    }

    /** Register a product in the catalog of the negocio. */
    public function store(ProductRequest $request, CreateProduct $createProduct): JsonResponse
    {
        $product = $createProduct->execute($request->toData());

        return new ProductResource($product)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', $request->url().'/'.$product->id->value);
    }

    /** Replace the data of a product of the negocio. */
    public function update(ProductRequest $request, string $producto, UpdateProduct $updateProduct): ProductResource
    {
        return new ProductResource($updateProduct->execute($producto, $request->toData()));
    }

    /** Take a product out of the catalog (soft delete). */
    public function destroy(string $producto, DeleteProduct $deleteProduct): Response
    {
        $deleteProduct->execute($producto);

        return response()->noContent();
    }
}
