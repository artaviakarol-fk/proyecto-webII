<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Domain\Exceptions;

use Src\Credit\Customers\Domain\ValueObjects\Colones;
use Src\Shared\Domain\Exceptions\DomainException;
use Src\Shared\Domain\ValueObjects\Money;

final class CreditLimitExceededException extends DomainException
{
    /** Build the exception with everything the cashier needs to explain it: the sale, what is owed, the limit and what is left. */
    public static function create(string $customer, Money $sale, Money $balance, Money $limit, Money $available): self
    {
        return new self('credit.limit_exceeded', [
            'customer' => $customer,
            'sale' => Colones::format($sale),
            'balance' => Colones::format($balance),
            'limit' => Colones::format($limit),
            'available' => Colones::format($available),
        ]);
    }
}
