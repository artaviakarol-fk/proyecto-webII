<?php

declare(strict_types=1);

namespace Src\Identity\Staff\Infrastructure\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Src\Identity\Staff\Application\DTOs\ChangeStaffAccessCommand;

final class ChangeStaffAccessRequest extends FormRequest
{
    /**
     * The new role, whether the account can log in, or both.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /** Un rol por defecto ("administrador", "cajero", "bodeguero", "supervisor") o el id de un rol del negocio. */
            'rol' => ['required_without:activo', 'string', 'max:60'],
            /** false desactiva la cuenta: no puede iniciar sesión y sus sesiones se cierran. */
            'activo' => ['required_without:rol', 'boolean'],
        ];
    }

    /** Build the command of the use case from the validated body. */
    public function toCommand(): ChangeStaffAccessCommand
    {
        return new ChangeStaffAccessCommand(
            $this->has('rol') ? $this->string('rol')->value() : null,
            $this->has('activo') ? $this->boolean('activo') : null,
        );
    }
}
