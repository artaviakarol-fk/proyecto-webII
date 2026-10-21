<?php

declare(strict_types=1);

namespace Tests\Support\Sales;

use Src\Sales\Sales\Application\Contracts\SaleReader;
use Src\Sales\Sales\Application\DTOs\SaleDetails;
use Src\Sales\Sales\Application\DTOs\SaleFilter;
use Src\Sales\Sales\Application\DTOs\SaleSummary;
use Src\Sales\Sales\Domain\ValueObjects\SaleId;
use Src\Shared\Domain\ValueObjects\NegocioId;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;
use Tests\Support\Shared\Pages;

final class InMemorySaleReader implements SaleReader
{
    /** @var list<array{string, SaleDetails}> Negocio id and sale, in the order they were opened. */
    private array $sales = [];

    /** Keep a sale of a negocio. */
    public function add(string $negocioId, SaleDetails $sale): void
    {
        $this->sales[] = [$negocioId, $sale];
    }

    /** Find a sale scoped to its negocio. */
    public function find(SaleId $id, NegocioId $negocioId): ?SaleDetails
    {
        foreach ($this->sales as [$negocio, $sale]) {
            if ($negocio === $negocioId->value && $sale->id === $id->value) {
                return $sale;
            }
        }

        return null;
    }

    /**
     * One page of the sales of the negocio, newest first.
     *
     * @return Page<SaleSummary>
     */
    public function list(NegocioId $negocioId, SaleFilter $filter, PageRequest $page): Page
    {
        $matches = [];

        foreach (array_reverse($this->sales) as [$negocio, $sale]) {
            $sameDay = $filter->day === null || str_starts_with($sale->createdAt, $filter->day->format('Y-m-d'));

            if ($negocio === $negocioId->value && ($filter->status === null || $filter->status->value === $sale->status) && $sameDay) {
                $matches[] = new SaleSummary(
                    $sale->id, $sale->status, $sale->paymentMethod, $sale->cashierId, $sale->cashierName,
                    count($sale->lines), $sale->total, $sale->createdAt, $sale->paidAt, $sale->cancelledAt,
                );
            }
        }

        return Pages::of($matches, $page);
    }
}
