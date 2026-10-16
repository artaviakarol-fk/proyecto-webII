<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class InvalidSaleStateException extends DomainException
{
    /** The sale cannot be changed or paid because it is no longer open. */
    public static function notOpen(): self
    {
        return new self('sales.sale_not_open');
    }

    /** The sale was cancelled before. */
    public static function alreadyCancelled(): self
    {
        return new self('sales.sale_already_cancelled');
    }
}
