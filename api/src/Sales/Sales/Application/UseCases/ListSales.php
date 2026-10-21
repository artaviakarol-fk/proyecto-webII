<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Application\UseCases;

use Src\Sales\Sales\Application\Contracts\SaleReader;
use Src\Sales\Sales\Application\DTOs\SaleFilter;
use Src\Sales\Sales\Application\DTOs\SaleSummary;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;

final readonly class ListSales
{
    /** Receive the negocio of the operation and the sale reader. */
    public function __construct(
        private NegocioContext $context,
        private SaleReader $reader,
    ) {}

    /**
     * One page of the sales of the current negocio, newest first.
     *
     * @return Page<SaleSummary>
     */
    public function execute(SaleFilter $filter, PageRequest $page): Page
    {
        return $this->reader->list($this->context->current(), $filter, $page);
    }
}
