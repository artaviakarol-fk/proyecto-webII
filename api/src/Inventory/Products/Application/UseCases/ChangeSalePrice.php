<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Application\UseCases;

use Src\Inventory\Products\Domain\Contracts\ProductRepository;
use Src\Inventory\Products\Domain\Entities\Product;
use Src\Inventory\Products\Domain\ValueObjects\PriceChangeReason;
use Src\Inventory\Shared\Domain\ValueObjects\Money;

final readonly class ChangeSalePrice
{
    /** Receive the use case that finds a product of the negocio and the product repository. */
    public function __construct(
        private GetProduct $getProduct,
        private ProductRepository $products,
    ) {}

    /**
     * Accept a new sale price for a product of the current negocio; the history records the change.
     * Keeping the current price needs no call at all.
     */
    public function execute(string $productId, string $salePrice): Product
    {
        $product = $this->getProduct->execute($productId);

        $product->changeSalePrice(Money::fromDecimal($salePrice));
        $this->products->save($product, PriceChangeReason::Repriced);

        return $product;
    }
}
