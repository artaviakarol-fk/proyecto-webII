<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Application\UseCases;

use DateTimeImmutable;
use Src\Inventory\Products\Application\Contracts\ExpiredUnitsCounter;
use Src\Inventory\Products\Application\Contracts\ReservedUnitsCounter;
use Src\Inventory\Products\Application\DTOs\CatalogItem;
use Src\Inventory\Products\Domain\Entities\Product;
use Src\Inventory\Products\Domain\ValueObjects\Availability;
use Src\Inventory\Shared\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;

final readonly class ListCatalog
{
    /** Receive the listing of the products of the negocio and the counters of expired and reserved units. */
    public function __construct(
        private ListProducts $listProducts,
        private ExpiredUnitsCounter $expiredUnits,
        private ReservedUnitsCounter $reservedUnits,
    ) {}

    /**
     * One page of what a cliente can buy in the current negocio: each product with the units that can really be
     * sold (the stock minus the units marked as expired) and the units other reservas hold at this moment. With a category, only its products.
     *
     * @return Page<CatalogItem>
     */
    public function execute(?string $term, PageRequest $page, DateTimeImmutable $now, ?string $category = null): Page
    {
        $products = $this->listProducts->execute($term, $page, $category);
        $first = $products->items[0] ?? null;
        if ($first === null) {
            return new Page([], $products->total, $products->request);
        }

        // Two grouped queries for the whole page instead of two queries for every product.
        $ids = array_map(fn (Product $product): ProductId => $product->id, $products->items);
        $expired = $this->expiredUnits->ofMany($ids, $first->negocioId);
        $reserved = $this->reservedUnits->ofMany($ids, $first->negocioId, $now);

        return $products->map(
            fn (Product $product): CatalogItem => new CatalogItem(
                $product,
                new Availability($product->details->stock, $expired[$product->id->value] ?? 0),
                $reserved[$product->id->value] ?? 0,
            ),
        );
    }
}
