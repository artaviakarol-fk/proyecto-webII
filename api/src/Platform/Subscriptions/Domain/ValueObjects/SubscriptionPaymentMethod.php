<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Domain\ValueObjects;

/** How a month of the platform was paid. */
enum SubscriptionPaymentMethod: string
{
    /** Cash given to the platform, registered by a superadmin. */
    case Efectivo = 'efectivo';

    /** A SINPE Móvil transfer to the number of the platform, registered by a superadmin. */
    case Sinpe = 'sinpe';

    /** A card charged through ONVO or PayPal by the administrator, confirmed with the provider. */
    case Tarjeta = 'tarjeta';

    /** SINPE Móvil through ONVO by the administrator, confirmed with the provider. */
    case SinpeMovil = 'sinpe_movil';

    /** Whether a superadmin registers it by hand, because the money did not go through a provider. */
    public function isManual(): bool
    {
        return $this === self::Efectivo || $this === self::Sinpe;
    }
}
