<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class SaleNotFoundException extends NotFoundException
{
    /** The sale to pay is not in the negocio. */
    public static function create(): self
    {
        return new self('payments.sale_not_found');
    }
}
