<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class CustomerNotFoundException extends NotFoundException
{
    /** Build the exception for a customer missing from the negocio. */
    public static function create(): self
    {
        return new self('credit.customer_not_found');
    }
}
