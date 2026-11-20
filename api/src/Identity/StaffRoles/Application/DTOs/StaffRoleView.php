<?php

declare(strict_types=1);

namespace Src\Identity\StaffRoles\Application\DTOs;

use Src\Identity\Users\Domain\ValueObjects\Permission;

final readonly class StaffRoleView
{
    /**
     * A role the administrator can give: a default one (its id is the name of the role, like "cajero") or one of the
     * negocio (its id is a uuid), with its permissions and how many accounts of the staff have it.
     *
     * @param  list<Permission>  $permissions
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $permissions,
        public bool $isDefault,
        public int $usage,
    ) {}
}
