<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\ValueObjects;

enum PaymentMethod: string
{
    case Card = 'tarjeta';
    case SinpeMovil = 'sinpe_movil';
}
