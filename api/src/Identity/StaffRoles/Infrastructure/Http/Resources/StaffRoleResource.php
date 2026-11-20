<?php

declare(strict_types=1);

namespace Src\Identity\StaffRoles\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Identity\StaffRoles\Application\DTOs\StaffRoleView;
use Src\Identity\Users\Domain\ValueObjects\Permission;

/**
 * @property-read StaffRoleView $resource
 */
final class StaffRoleResource extends JsonResource
{
    /** Wrap a role the administrator can give. */
    public function __construct(private readonly StaffRoleView $role)
    {
        parent::__construct($role);
    }

    /**
     * The role, its permissions, whether it is a default one (which cannot be changed) and how many people have it.
     *
     * @return array{id: string, nombre: string, permisos: list<string>, predeterminado: bool, en_uso: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->role->id,
            'nombre' => $this->role->name,
            'permisos' => array_map(fn (Permission $permission): string => $permission->value, $this->role->permissions),
            'predeterminado' => $this->role->isDefault,
            'en_uso' => $this->role->usage,
        ];
    }
}
