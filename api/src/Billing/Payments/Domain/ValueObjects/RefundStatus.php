<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\ValueObjects;

/** What the provider answered about a refund: it is in course, it is done or it could not be made. */
enum RefundStatus: string
{
    case Pending = 'pendiente';
    case Succeeded = 'completado';
    case Failed = 'fallido';
}
