<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Application\UseCases;

use Src\Inventory\Products\Application\Contracts\PriceHistoryReader;
use Src\Inventory\Products\Application\DTOs\PriceHistoryEntry;

final readonly class GetPriceHistory
{
    /** Receive the use case that finds a product of the negocio and the history reader. */
    public function __construct(
        private GetProduct $getProduct,
        private PriceHistoryReader $history,
    ) {}

    /**
     * The history of a product of the current negocio, newest first; a product of another negocio is missing.
     *
     * @return list<PriceHistoryEntry>
     */
    public function execute(string $productId): array
    {
        $product = $this->getProduct->execute($productId);

        return $this->history->of($product->id, $product->negocioId);
    }
}
