<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\AuthenticationFailedException;

final class InvalidWebhookException extends AuthenticationFailedException
{
    /** The notification does not carry a signature or a secret that the provider proves as its own. */
    public static function create(): self
    {
        return new self('payments.webhook_invalid');
    }
}
