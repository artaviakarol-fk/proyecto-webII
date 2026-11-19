<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Domain\ValueObjects;

/** Where a negocio is with the rent of the platform. */
enum ServiceStatus: string
{
    /** Paid (or in its free trial) and far from the date. */
    case Activo = 'activo';

    /** It still works, but the date is close: the administrator sees a yellow warning. */
    case PorVencer = 'por_vencer';

    /** The date passed, and for a few days everything still works: the administrator sees a red warning. */
    case EnGracia = 'en_gracia';

    /** The grace ended or the platform suspended it: only the administrator can enter, to see and to pay. */
    case Suspendido = 'suspendido';

    /** Whether the negocio can still sell, receive merchandise and serve its clientes. */
    public function serves(): bool
    {
        return $this !== self::Suspendido;
    }
}
