<?php

declare(strict_types=1);

namespace Src\Sales\Reports\Application\Contracts;

use DateTimeImmutable;
use Src\Shared\Domain\ValueObjects\Money;
use Src\Shared\Domain\ValueObjects\NegocioId;

interface DailyClosingReader
{
    /**
     * What the negocio collected on a day, by way of paying: only paid sales count, on the day they were paid, and
     * a cancelled or still open one never does. A way of paying with no sales is left out.
     *
     * @return array<string, array{count: int, total: Money}> Keyed by the way of paying.
     */
    public function collectedOn(NegocioId $negocioId, DateTimeImmutable $day): array;
}
