<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\ServiceUnavailableException;

final class GatewayUnavailableException extends ServiceUnavailableException
{
    /** The provider did not answer in time or answered with an error that is not a refusal. */
    public static function create(): self
    {
        return new self('payments.gateway_unavailable');
    }

    /** The provider has no valid credentials in this installation, so it cannot charge. */
    public static function notConfigured(): self
    {
        return new self('payments.gateway_not_configured');
    }
}
