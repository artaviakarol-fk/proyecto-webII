<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Application\UseCases;

use Src\Inventory\Products\Domain\Contracts\ProductRepository;
use Src\Inventory\Products\Domain\Exceptions\ProductNotFoundException;
use Src\Inventory\Products\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class DeleteProduct
{
    /** Receive the negocio of the operation and the product repository. */
    public function __construct(
        private NegocioContext $context,
        private ProductRepository $products,
    ) {}

    /** Take a product of the current negocio out of the catalog without losing its history. */
    public function execute(string $productId): void
    {
        $product = $this->products->find(new ProductId($productId), $this->context->current())
            ?? throw ProductNotFoundException::create();

        $this->products->delete($product);
    }
}
