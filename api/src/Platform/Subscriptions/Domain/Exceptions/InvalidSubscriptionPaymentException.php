<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final class InvalidSubscriptionPaymentException extends InvalidValueException
{
    /** Cash and SINPE to the number of the platform are registered by a superadmin, not paid from the app. */
    public static function manualMethod(): self
    {
        return new self('platform.manual_method');
    }

    /** A payment registered by hand is cash or SINPE to the number of the platform. */
    public static function providerMethod(): self
    {
        return new self('platform.provider_method');
    }
}
