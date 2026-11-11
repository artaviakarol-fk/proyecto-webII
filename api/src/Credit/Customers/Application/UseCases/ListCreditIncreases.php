<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\UseCases;

use Src\Credit\Customers\Application\Contracts\CreditIncreaseReader;
use Src\Credit\Customers\Application\DTOs\CreditIncreaseFilter;
use Src\Credit\Customers\Application\DTOs\CreditIncreaseSummary;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;

/** The administration reviews the increases the cashiers gave: who, when, how much and why. */
final readonly class ListCreditIncreases
{
    /** Receive the negocio of the operation and the reader. */
    public function __construct(
        private NegocioContext $context,
        private CreditIncreaseReader $reader,
    ) {}

    /**
     * One page of the increases of the current negocio, the newest first.
     *
     * @return Page<CreditIncreaseSummary>
     */
    public function execute(CreditIncreaseFilter $filter, PageRequest $page): Page
    {
        return $this->reader->list($this->context->current(), $filter, $page);
    }
}
