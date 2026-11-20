<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class SubscriptionNotFoundException extends NotFoundException
{
    /** The negocio has no rent on the platform. */
    public static function create(): self
    {
        return new self('platform.subscription_not_found');
    }

    /** The payment is not one of the negocio. */
    public static function payment(): self
    {
        return new self('platform.subscription_payment_not_found');
    }
}
