<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Domain\Exceptions;

use Src\Credit\Customers\Domain\ValueObjects\Colones;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\Money;

final class CreditIncreaseAboveMaximumException extends InvalidValueException
{
    /** Build the exception with the limit it would leave, the maximum and how much can still be given. */
    public static function create(string $customer, Money $increase, Money $wouldBe, Money $maximum, Money $left): self
    {
        return new self('credit.increase_above_maximum', [
            'customer' => $customer,
            'increase' => Colones::format($increase),
            'would_be' => Colones::format($wouldBe),
            'max' => Colones::format($maximum),
            'left' => Colones::format($left),
        ]);
    }
}
