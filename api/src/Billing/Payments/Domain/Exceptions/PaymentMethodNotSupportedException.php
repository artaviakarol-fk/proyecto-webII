<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final class PaymentMethodNotSupportedException extends InvalidValueException
{
    /** The provider of the configuration does not offer this way of paying. */
    public static function create(string $method, string $provider): self
    {
        return new self('payments.method_not_supported', ['method' => $method, 'provider' => $provider]);
    }
}
