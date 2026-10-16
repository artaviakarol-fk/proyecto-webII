<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Domain\ValueObjects;

enum SaleStatus: string
{
    case Open = 'abierta';
    case Paid = 'cobrada';
    case Cancelled = 'anulada';

    /** Tell whether the units of the sale are out of the stock while it has this status. */
    public function holdsStock(): bool
    {
        return $this !== self::Cancelled;
    }
}
