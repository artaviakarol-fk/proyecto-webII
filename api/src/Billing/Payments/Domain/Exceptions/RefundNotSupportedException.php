<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class RefundNotSupportedException extends DomainException
{
    /** The provider of the configuration does not give money back from this system. */
    public static function for(string $provider): self
    {
        return new self('payments.refund_not_supported', ['provider' => $provider]);
    }
}
