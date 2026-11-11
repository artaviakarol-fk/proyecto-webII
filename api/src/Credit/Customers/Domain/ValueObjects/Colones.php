<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Domain\ValueObjects;

use Src\Shared\Domain\ValueObjects\Money;

/** Writes an amount the way the messages to the cashier show it: ₡90 000 or ₡1 250,50. */
final class Colones
{
    /** Format the amount with a space between thousands and the céntimos only when there are any. */
    public static function format(Money $amount): string
    {
        $decimals = $amount->cents % 100 === 0 ? 0 : 2;

        return '₡'.number_format($amount->cents / 100, $decimals, ',', ' ');
    }
}
