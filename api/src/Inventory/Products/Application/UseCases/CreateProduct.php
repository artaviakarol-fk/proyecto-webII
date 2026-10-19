<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Application\UseCases;

use Src\Inventory\Products\Application\DTOs\ProductData;
use Src\Inventory\Products\Domain\Contracts\ProductRepository;
use Src\Inventory\Products\Domain\Entities\Product;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class CreateProduct
{
    use BuildsProductDetails;

    /** Receive the negocio of the operation and the product repository. */
    public function __construct(
        private NegocioContext $context,
        private ProductRepository $products,
    ) {}

    /** Register a product in the catalog of the current negocio. */
    public function execute(ProductData $data): Product
    {
        $product = Product::create($this->products->nextId(), $this->context->current(), $this->details($data));

        $this->products->save($product);

        return $product;
    }
}
