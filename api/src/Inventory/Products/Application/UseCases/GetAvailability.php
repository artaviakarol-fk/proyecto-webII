<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Application\UseCases;

use Src\Inventory\Products\Application\Contracts\ExpiredUnitsCounter;
use Src\Inventory\Products\Domain\Entities\Product;
use Src\Inventory\Products\Domain\ValueObjects\Availability;

final readonly class GetAvailability
{
    /** Receive the use case that finds a product of the negocio and the expired units counter. */
    public function __construct(
        private GetProduct $getProduct,
        private ExpiredUnitsCounter $expiredUnits,
    ) {}

    /**
     * The sellable stock of a product of the current negocio; one of another negocio is missing.
     *
     * @return array{Product, Availability}
     */
    public function execute(string $productId): array
    {
        $product = $this->getProduct->execute($productId);

        return [$product, new Availability($product->details->stock, $this->expiredUnits->of($product->id, $product->negocioId))];
    }
}
