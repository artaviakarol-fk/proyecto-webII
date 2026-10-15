<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Infrastructure\Http\Controllers;

use Src\Inventory\Products\Application\UseCases\ChangeProductPhoto;
use Src\Inventory\Products\Application\UseCases\GetProductPhoto;
use Src\Inventory\Products\Infrastructure\Http\Resources\ProductResource;
use Src\Shared\Infrastructure\Http\Requests\UploadImageRequest;
use Src\Shared\Infrastructure\Storage\DiskImageStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ProductPhotoController
{
    /** Upload or replace the photo of a product of the negocio (multipart field "imagen"). */
    public function store(UploadImageRequest $request, string $producto, ChangeProductPhoto $changePhoto): ProductResource
    {
        return new ProductResource($changePhoto->execute($producto, $request->toUpload()));
    }

    /** Send the photo of a product of the negocio; a product of another negocio answers 404. */
    public function show(string $producto, GetProductPhoto $getPhoto): StreamedResponse
    {
        return DiskImageStorage::disk()->response($getPhoto->execute($producto));
    }
}
