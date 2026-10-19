<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Application\UseCases;

use Src\Inventory\Products\Domain\Contracts\ProductRepository;
use Src\Inventory\Products\Domain\Entities\Product;
use Src\Inventory\Products\Domain\Exceptions\ProductNotFoundException;
use Src\Inventory\Shared\Domain\ValueObjects\ProductId;
use Src\Shared\Application\DTOs\ImageUpload;
use Src\Shared\Domain\Contracts\ImageStorage;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\ImageFolders;

final readonly class ChangeProductPhoto
{
    /** Receive the negocio of the operation, the product repository and the image storage. */
    public function __construct(
        private NegocioContext $context,
        private ProductRepository $products,
        private ImageStorage $images,
    ) {}

    /** Store the photo in the folder of the negocio, point the product to it and remove the replaced one. */
    public function execute(string $productId, ImageUpload $image): Product
    {
        $negocioId = $this->context->current();
        $product = $this->products->find(new ProductId($productId), $negocioId)
            ?? throw ProductNotFoundException::create();

        $path = $this->images->store(ImageFolders::product($negocioId, $product->id->value), $image->sourcePath, $image->extension);
        $replaced = $product->changePhoto($path);
        $this->products->save($product);

        if ($replaced !== null) {
            $this->images->delete($replaced);
        }

        return $product;
    }
}
