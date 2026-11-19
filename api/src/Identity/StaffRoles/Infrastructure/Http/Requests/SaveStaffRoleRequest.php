<?php

declare(strict_types=1);

namespace Src\Identity\StaffRoles\Infrastructure\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Identity\StaffRoles\Application\DTOs\SaveStaffRoleCommand;
use Src\Identity\Users\Domain\Entities\StaffRole;
use Src\Identity\Users\Domain\ValueObjects\Permission;

final class SaveStaffRoleRequest extends FormRequest
{
    /**
     * The name of the role and at least one of the permissions that exist.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /** Nombre del rol, por ejemplo "Encargado de turno". No puede llamarse como un rol por defecto. */
            'nombre' => ['required', 'string', 'max:'.StaffRole::NAME_MAX_LENGTH],
            /** Permisos del rol: "administrar", "caja" o "inventario". Al menos uno. */
            'permisos' => ['required', 'array', 'min:1'],
            'permisos.*' => ['required', 'string', Rule::enum(Permission::class)],
        ];
    }

    /** Build the command of the use case from the validated body. */
    public function toCommand(): SaveStaffRoleCommand
    {
        /** @var list<string> $permissions */
        $permissions = array_values(array_map('strval', (array) $this->input('permisos')));

        return new SaveStaffRoleCommand($this->string('nombre')->value(), $permissions);
    }
}
