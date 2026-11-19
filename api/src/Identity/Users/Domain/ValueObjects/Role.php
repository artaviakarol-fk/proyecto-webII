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

    /** Default role for whoever attends the till and also receives the merchandise. */
    case Supervisor = 'supervisor';

    /** A role that the negocio created itself; its permissions are the ones of that role, see CustomRole. */
    case Personalizado = 'personalizado';

    case Cliente = 'cliente';

    /**
     * The roles of the people who work in the negocio; the cliente is a buyer, not part of the staff.
     *
     * @return list<self>
     */
    public static function staff(): array
    {
        return [self::Administrador, self::Cajero, self::Bodeguero, self::Supervisor, self::Personalizado];
    }

    /**
     * The roles that every negocio has from the start and that nobody can change. An administrator gives them to
     * its staff or creates roles of its own.
     *
     * @return list<self>
     */
    public static function defaults(): array
    {
        return [self::Administrador, self::Cajero, self::Bodeguero, self::Supervisor];
    }

    /**
     * Abilities granted to the access tokens of the role. A role of the negocio has none here: its permissions are
     * the ones the negocio chose for it.
     *
     * @return list<string>
     */
    public function abilities(): array
    {
        return match ($this) {
            self::SuperAdmin => ['plataforma'],
            self::Administrador => [Permission::Administrar->value, Permission::Caja->value, Permission::Inventario->value],
            self::Cajero => [Permission::Caja->value],
            self::Bodeguero => [Permission::Inventario->value],
            self::Supervisor => [Permission::Caja->value, Permission::Inventario->value],
            self::Personalizado => [],
            self::Cliente => ['comprar'],
        };
    }

    /** The name a person reads. */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Superadmin',
            self::Administrador => 'Administrador',
            self::Cajero => 'Cajero',
            self::Bodeguero => 'Bodeguero',
            self::Supervisor => 'Supervisor',
            self::Personalizado => 'Personalizado',
            self::Cliente => 'Cliente',
        };
    }
}
