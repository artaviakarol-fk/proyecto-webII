<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class CreditPaymentNotFoundException extends NotFoundException
{
    /** Build the exception for an abono missing from the negocio. */
    public static function create(): self
    {
        return new self('credit.payment_not_found');
    }
}
