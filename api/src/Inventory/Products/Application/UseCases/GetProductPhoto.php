<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Application\UseCases;

use Src\Inventory\Products\Domain\Exceptions\ProductPhotoNotFoundException;

final readonly class GetProductPhoto
{
    /** Receive the use case that finds a product of the current negocio. */
    public function __construct(private GetProduct $getProduct) {}

    /** Answer the stored path of the photo of a product of the current negocio. */
    public function execute(string $productId): string
    {
        return $this->getProduct->execute($productId)->photoPath ?? throw ProductPhotoNotFoundException::create();
    }
}
