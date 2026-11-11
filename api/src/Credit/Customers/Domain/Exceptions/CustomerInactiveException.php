<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class CustomerInactiveException extends DomainException
{
    /** Build the exception for a deactivated customer who tries to buy on fiado. */
    public static function create(string $customer): self
    {
        return new self('credit.customer_inactive', ['customer' => $customer]);
    }
}
