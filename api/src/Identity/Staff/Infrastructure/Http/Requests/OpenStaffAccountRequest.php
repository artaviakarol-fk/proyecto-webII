<?php

declare(strict_types=1);

namespace Src\Identity\Staff\Infrastructure\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Src\Identity\Staff\Application\DTOs\OpenStaffAccountCommand;
use Src\Identity\Users\Domain\Entities\User;
use Src\Identity\Users\Domain\ValueObjects\Email;
use Src\Identity\Users\Infrastructure\Http\Requests\RegisterClienteRequest;

final class OpenStaffAccountRequest extends FormRequest
{
    /**
     * The person, its temporary password and its role.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:'.User::NAME_MAX_LENGTH],
            'correo' => ['required', 'string', 'email:filter', 'max:'.Email::MAX_LENGTH],
            /** Contraseña temporal: la persona la tiene que cambiar la primera vez que entra. */
            'contrasena_temporal' => ['required', 'string', 'min:'.RegisterClienteRequest::PASSWORD_MIN_LENGTH, 'max:'.RegisterClienteRequest::PASSWORD_MAX_LENGTH],
            /** Un rol por defecto ("administrador", "cajero", "bodeguero", "supervisor") o el id de un rol del negocio. */
            'rol' => ['required', 'string', 'max:60'],
        ];
    }

    /** Build the command of the use case from the validated body. */
    public function toCommand(): OpenStaffAccountCommand
    {
        return new OpenStaffAccountCommand(
            $this->string('nombre')->value(),
            $this->string('correo')->value(),
            $this->string('contrasena_temporal')->value(),
            $this->string('rol')->value(),
        );
    }
}
