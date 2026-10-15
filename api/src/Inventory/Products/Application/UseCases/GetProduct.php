<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Application\UseCases;

use Src\Inventory\Products\Domain\Contracts\ProductRepository;
use Src\Inventory\Products\Domain\Entities\Product;
use Src\Inventory\Products\Domain\Exceptions\ProductNotFoundException;
use Src\Inventory\Products\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class GetProduct
{
    /** Receive the negocio of the operation and the product repository. */
    public function __construct(
        private NegocioContext $context,
        private ProductRepository $products,
    ) {}

    /** Answer a product of the current negocio; one of another negocio is reported as missing. */
    public function execute(string $productId): Product
    {
        return $this->products->find(new ProductId($productId), $this->context->current())
            ?? throw ProductNotFoundException::create();
    }
}
