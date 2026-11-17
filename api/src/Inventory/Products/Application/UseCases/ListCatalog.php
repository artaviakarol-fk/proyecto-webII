<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Application\UseCases;

use Src\Inventory\Products\Application\Contracts\ExpiredUnitsCounter;
use Src\Inventory\Products\Application\DTOs\CatalogItem;
use Src\Inventory\Products\Domain\Entities\Product;
use Src\Inventory\Products\Domain\ValueObjects\Availability;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;

final readonly class ListCatalog
{
    /** Receive the listing of the products of the negocio and the expired units counter. */
    public function __construct(
        private ListProducts $listProducts,
        private ExpiredUnitsCounter $expiredUnits,
    ) {}

    /**
     * One page of what a cliente can buy in the current negocio: each product with the units that can really be
     * sold, which are the stock minus the units marked as expired.
     *
     * @return Page<CatalogItem>
     */
    public function execute(?string $term, PageRequest $page): Page
    {
        return $this->listProducts->execute($term, $page)->map(
            fn (Product $product): CatalogItem => new CatalogItem(
                $product,
                new Availability($product->details->stock, $this->expiredUnits->of($product->id, $product->negocioId)),
            ),
        );
    }
}
