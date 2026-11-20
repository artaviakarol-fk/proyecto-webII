<?php

declare(strict_types=1);

namespace Src\Platform\Tenants\Infrastructure\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Src\Identity\Users\Domain\Entities\User;
use Src\Identity\Users\Domain\ValueObjects\Email;
use Src\Identity\Users\Infrastructure\Http\Requests\RegisterClienteRequest;
use Src\Negocios\Profile\Domain\Entities\Negocio;
use Src\Platform\Tenants\Application\DTOs\RegisterNegocioCommand;

final class RegisterNegocioRequest extends FormRequest
{
    /**
     * The new negocio and its first administrator.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /** Nombre del negocio, por ejemplo "Minisúper La Esquina". */
            'nombre' => ['required', 'string', 'max:'.Negocio::NAME_MAX_LENGTH],
            /** Nombre de la persona que va a administrar el negocio. */
            'administrador.nombre' => ['required', 'string', 'max:'.User::NAME_MAX_LENGTH],
            /** Correo con el que el administrador inicia sesión. */
            'administrador.correo' => ['required', 'string', 'email:filter', 'max:'.Email::MAX_LENGTH],
            /** Contraseña temporal: el administrador la tiene que cambiar la primera vez que entra. */
            'administrador.contrasena_temporal' => ['required', 'string', 'min:'.RegisterClienteRequest::PASSWORD_MIN_LENGTH, 'max:'.RegisterClienteRequest::PASSWORD_MAX_LENGTH],
        ];
    }

    /** Build the command of the use case from the validated body. */
    public function toCommand(): RegisterNegocioCommand
    {
        return new RegisterNegocioCommand(
            $this->string('nombre')->value(),
            $this->string('administrador.nombre')->value(),
            $this->string('administrador.correo')->value(),
            $this->string('administrador.contrasena_temporal')->value(),
        );
    }
}
