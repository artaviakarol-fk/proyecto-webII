<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Application\UseCases;

use Src\Sales\Sales\Application\Contracts\ProductCatalog;
use Src\Sales\Sales\Application\DTOs\SaleLineData;
use Src\Sales\Sales\Domain\Exceptions\SaleProductUnavailableException;
use Src\Sales\Sales\Domain\ValueObjects\SaleLine;
use Src\Sales\Shared\Domain\ValueObjects\Money;
use Src\Sales\Shared\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class PriceSaleLines
{
    /** Receive the catalog that knows the sale price of each product. */
    public function __construct(private ProductCatalog $catalog) {}

    /**
     * Price each requested line with the price the catalog has now; the client never sends a price.
     *
     * @param  list<SaleLineData>  $lines
     * @return list<SaleLine>
     */
    public function execute(array $lines, NegocioId $negocioId): array
    {
        $products = $this->catalog->findMany(array_map(fn (SaleLineData $line): string => $line->productId, $lines), $negocioId);

        return array_map(function (SaleLineData $line) use ($products): SaleLine {
            $product = $products[$line->productId] ?? throw SaleProductUnavailableException::create();

            return new SaleLine(new ProductId($product->id), $line->quantity, Money::fromDecimal($product->salePrice), $product->liquor);
        }, $lines);
    }
}
