<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class PaymentNotFoundException extends NotFoundException
{
    /** The sale has no payment in the negocio. */
    public static function create(): self
    {
        return new self('payments.payment_not_found');
    }
}
