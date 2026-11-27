<?php

declare(strict_types=1);

namespace Src\Inventory\Restock\Application\UseCases;

use DateTimeImmutable;
use Src\Inventory\Restock\Application\Contracts\ProductSalesReader;
use Src\Inventory\Restock\Application\DTOs\ProductSales;
use Src\Inventory\Restock\Application\DTOs\RestockItem;
use Src\Inventory\Restock\Application\DTOs\RestockReport;
use Src\Inventory\Restock\Domain\Services\RestockAdvisor;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class GetRestockReport
{
    /** How many best sellers the report shows. */
    public const int TOP_SELLERS = 5;

    /** Receive the negocio of the operation, the reader of the sales and the advisor. */
    public function __construct(
        private NegocioContext $context,
        private ProductSalesReader $reader,
        private RestockAdvisor $advisor,
    ) {}

    /**
     * What the products of the current negocio sold in the last days, how long their stock lasts and how many units to
     * order. The most urgent come first; it only reads, it never orders anything.
     */
    public function execute(int $windowDays, DateTimeImmutable $now): RestockReport
    {
        $since = $now->modify("-{$windowDays} days");

        $items = array_map(function (ProductSales $product) use ($windowDays): RestockItem {
            $advice = $this->advisor->advise($product->stock, $product->sold, $windowDays);

            return new RestockItem(
                $product->id,
                $product->code,
                $product->name,
                $product->category,
                $product->stock,
                $product->sold,
                $advice->averagePerDay,
                $advice->daysLeft,
                $advice->status,
                $advice->suggested,
            );
        }, $this->reader->soldSince($this->context->current(), $since));

        $topSellers = array_values(array_filter($items, static fn (RestockItem $item): bool => $item->sold > 0));
        usort($topSellers, static fn (RestockItem $a, RestockItem $b): int => [$b->sold, $a->name] <=> [$a->sold, $b->name]);

        usort($items, static fn (RestockItem $a, RestockItem $b): int => [
            $a->status->rank(), $a->daysLeft ?? INF, -$a->sold, $a->name,
        ] <=> [
            $b->status->rank(), $b->daysLeft ?? INF, -$b->sold, $b->name,
        ]);

        return new RestockReport($windowDays, $items, array_slice($topSellers, 0, self::TOP_SELLERS));
    }
}
