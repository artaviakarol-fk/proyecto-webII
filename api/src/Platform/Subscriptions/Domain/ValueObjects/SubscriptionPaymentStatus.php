<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Domain\ValueObjects;

enum SubscriptionPaymentStatus: string
{
    case Pending = 'pendiente';
    case Approved = 'aprobado';
    case Rejected = 'rechazado';
}
