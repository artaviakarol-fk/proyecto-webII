<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\ValueObjects;

/**
 * What a role of the staff can be allowed to do. They are the abilities the routes already check, so a role made of
 * them can never ask for something the API does not know how to check.
 */
enum Permission: string
{
    /** Manage the negocio: prices, reports, the staff and its roles. */
    case Administrar = 'administrar';

    /** Sell, charge, sell on credit and receive payments of the credit. */
    case Caja = 'caja';

    /** Receive merchandise and mark expired units. */
    case Inventario = 'inventario';
}
