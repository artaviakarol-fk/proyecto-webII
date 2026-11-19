<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\ValueObjects;

enum Role: string
{
    /** Runs the platform: opens negocios and their first administrator. It never sees the data of a negocio. */
    case SuperAdmin = 'superadmin';

    case Administrador = 'administrador';
    case Cajero = 'cajero';
    case Bodeguero = 'bodeguero';
    case Cliente = 'cliente';

    /**
     * The roles of the people who work in the negocio; the cliente is a buyer, not part of the staff.
     *
     * @return list<self>
     */
    public static function staff(): array
    {
        return [self::Administrador, self::Cajero, self::Bodeguero];
    }

    /**
     * Abilities granted to the access tokens of the role.
     *
     * @return list<string>
     */
    public function abilities(): array
    {
        return match ($this) {
            self::SuperAdmin => ['plataforma'],
            self::Administrador => ['administrar', 'caja', 'inventario'],
            self::Cajero => ['caja'],
            self::Bodeguero => ['inventario'],
            self::Cliente => ['comprar'],
        };
    }
}
