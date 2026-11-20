<?php

declare(strict_types=1);

namespace Src\Identity\Staff\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Identity\Users\Domain\Entities\User;

/**
 * @property-read User $resource
 */
final class StaffResource extends JsonResource
{
    /** Wrap an account of the staff. */
    public function __construct(private readonly User $user)
    {
        parent::__construct($user);
    }

    /**
     * The person, its role and what it allows, whether it can log in and whether it still has a temporary password.
     *
     * @return array{id: string, nombre: string, correo: string, rol: string, rol_id: string|null, rol_nombre: string, permisos: list<string>, activo: bool, debe_cambiar_contrasena: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->user->id->value,
            'nombre' => $this->user->name,
            'correo' => $this->user->email->value,
            'rol' => $this->user->role->value,
            'rol_id' => $this->user->customRole?->id,
            'rol_nombre' => $this->user->roleName(),
            'permisos' => $this->user->abilities(),
            'activo' => $this->user->active,
            'debe_cambiar_contrasena' => $this->user->mustChangePassword,
        ];
    }
}
