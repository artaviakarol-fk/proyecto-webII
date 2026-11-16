<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Application\DTOs;

/** What happened with a notification of the provider. */
enum WebhookOutcome: string
{
    /** The payment was brought up to date with what the provider says. */
    case Processed = 'procesado';

    /** The same event was already processed: nothing changed. */
    case Duplicate = 'duplicado';

    /** The event is not about a payment that this system follows. */
    case Ignored = 'ignorado';
}
