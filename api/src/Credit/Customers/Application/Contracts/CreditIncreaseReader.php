<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\Contracts;

use DateTimeImmutable;
use Src\Credit\Customers\Application\DTOs\CreditIncreaseFilter;
use Src\Credit\Customers\Application\DTOs\CreditIncreaseSummary;
use Src\Credit\Customers\Domain\ValueObjects\CreditIncreaseId;
use Src\Shared\Domain\ValueObjects\NegocioId;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;

interface CreditIncreaseReader
{
    /** One increase of the negocio as the administration reviews it. */
    public function find(CreditIncreaseId $id, NegocioId $negocioId, DateTimeImmutable $at): ?CreditIncreaseSummary;

    /**
     * One page of the increases of the negocio, the newest first.
     *
     * @return Page<CreditIncreaseSummary>
     */
    public function list(NegocioId $negocioId, CreditIncreaseFilter $filter, PageRequest $page): Page;
}
