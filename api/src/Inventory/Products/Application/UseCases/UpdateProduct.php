<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Application\UseCases;

use Src\Inventory\Products\Application\DTOs\ProductData;
use Src\Inventory\Products\Domain\Contracts\ProductRepository;
use Src\Inventory\Products\Domain\Entities\Product;
use Src\Inventory\Products\Domain\Exceptions\ProductNotFoundException;
use Src\Inventory\Shared\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class UpdateProduct
{
    use BuildsProductDetails;

    /** Receive the negocio of the operation and the product repository. */
    public function __construct(
        private NegocioContext $context,
        private ProductRepository $products,
    ) {}

    /** Replace the data of a product of the current negocio. */
    public function execute(string $productId, ProductData $data): Product
    {
        $product = $this->products->find(new ProductId($productId), $this->context->current())
            ?? throw ProductNotFoundException::create();

        $product->revise($this->details($data));
        $this->products->save($product);

        return $product;
    }
}
