<?php

declare(strict_types=1);

namespace Src\Inventory\Expirations\Domain\ValueObjects;

enum ExpiredUnitsStatus: string
{
    /** The units are still on the shelf: they stay in the stock but cannot be sold. */
    case Active = 'activa';

    /** The units left the shelf, for example when they went back to the supplier. */
    case Retired = 'retirada';
}
