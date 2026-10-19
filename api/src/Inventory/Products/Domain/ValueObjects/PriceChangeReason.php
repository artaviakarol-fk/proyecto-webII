<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Domain\ValueObjects;

/** Why the cost or the sale price of a product changed, as the price history records it. */
enum PriceChangeReason: string
{
    case Edited = 'edicion';
    case Repriced = 'cambio_precio';
}
