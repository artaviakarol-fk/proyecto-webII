<?php

declare(strict_types=1);

namespace Src\Identity\StaffRoles\Application\DTOs;

final readonly class SaveStaffRoleCommand
{
    /**
     * The name of the role and the permissions it gives, by their names ("administrar", "caja", "inventario").
     *
     * @param  list<string>  $permissions
     */
    public function __construct(
        public string $name,
        public array $permissions,
    ) {}
}
