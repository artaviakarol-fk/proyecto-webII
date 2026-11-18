<?php

declare(strict_types=1);

namespace Src\Inventory\Expirations\Application\Contracts;

use Src\Inventory\Expirations\Application\DTOs\ExpiredUnitsSummary;
use Src\Shared\Domain\ValueObjects\NegocioId;

interface ExpiredUnitsReader
{
    /**
     * The expired units of the negocio that are still on the shelf, the most recent first.
     *
     * @return list<ExpiredUnitsSummary>
     */
    public function onTheShelf(NegocioId $negocioId): array;
}
