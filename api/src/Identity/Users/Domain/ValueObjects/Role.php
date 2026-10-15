<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\ValueObjects;

enum Role: string
{
    case Administrador = 'administrador';
    case Cajero = 'cajero';
    case Bodeguero = 'bodeguero';

    /**
     * Abilities granted to the access tokens of the role.
     *
     * @return list<string>
     */
    public function abilities(): array
    {
        return match ($this) {
            self::Administrador => ['administrar', 'caja', 'inventario'],
            self::Cajero => ['caja'],
            self::Bodeguero => ['inventario'],
        };
    }
}
