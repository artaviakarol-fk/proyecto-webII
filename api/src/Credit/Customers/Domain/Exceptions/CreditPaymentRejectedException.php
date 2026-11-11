<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Domain\Exceptions;

use Src\Credit\Customers\Domain\ValueObjects\Colones;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\Money;

final class CreditPaymentRejectedException extends InvalidValueException
{
    /** The abono is larger than what the customer owes: the balance can never be negative. */
    public static function aboveBalance(string $customer, Money $amount, Money $balance): self
    {
        return new self('credit.payment_above_balance', [
            'customer' => $customer,
            'amount' => Colones::format($amount),
            'balance' => Colones::format($balance),
        ]);
    }

    /** The customer owes nothing. */
    public static function nothingOwed(string $customer): self
    {
        return new self('credit.payment_nothing_owed', ['customer' => $customer]);
    }

    /** The abono has to be more than zero. */
    public static function notPositive(): self
    {
        return new self('credit.payment_not_positive');
    }

    /** Only cash for now. */
    public static function methodNotAvailable(string $method): self
    {
        return new self('credit.payment_method_not_available', ['method' => $method]);
    }
}
