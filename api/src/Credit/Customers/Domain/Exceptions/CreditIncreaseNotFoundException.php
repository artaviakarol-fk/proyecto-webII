<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class CreditIncreaseNotFoundException extends NotFoundException
{
    /** Build the exception for an increase missing from the negocio. */
    public static function create(): self
    {
        return new self('credit.increase_not_found');
    }
}
