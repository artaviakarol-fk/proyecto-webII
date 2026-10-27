<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class SaleNotPayableException extends DomainException
{
    /** Only an open sale can be paid. */
    public static function create(): self
    {
        return new self('payments.sale_not_payable');
    }
}
