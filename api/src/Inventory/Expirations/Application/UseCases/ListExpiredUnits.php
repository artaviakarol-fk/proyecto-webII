<?php

declare(strict_types=1);

namespace Src\Inventory\Expirations\Application\UseCases;

use Src\Inventory\Expirations\Application\Contracts\ExpiredUnitsReader;
use Src\Inventory\Expirations\Application\DTOs\ExpiredUnitsSummary;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class ListExpiredUnits
{
    /** Receive the negocio of the operation and the reader of expired units. */
    public function __construct(
        private NegocioContext $context,
        private ExpiredUnitsReader $reader,
    ) {}

    /**
     * The units of the current negocio found expired that are still on the shelf.
     *
     * @return list<ExpiredUnitsSummary>
     */
    public function execute(): array
    {
        return $this->reader->onTheShelf($this->context->current());
    }
}
