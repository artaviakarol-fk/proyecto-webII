<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Identity\Users\Application\DTOs\RegisterClienteCommand;
use Src\Identity\Users\Domain\Entities\User;
use Src\Identity\Users\Domain\ValueObjects\Email;

final class RegisterClienteRequest extends FormRequest
{
    public const int PASSWORD_MIN_LENGTH = 8;

    /** Bcrypt only reads the first 72 bytes of a password. */
    public const int PASSWORD_MAX_LENGTH = 72;

    /**
     * Validation rules for the data of the new account. There is no rule for the role: it is never read.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:'.User::NAME_MAX_LENGTH],
            'correo' => ['required', 'string', 'email:filter', 'max:'.Email::MAX_LENGTH],
            'contrasena' => ['required', 'string', 'min:'.self::PASSWORD_MIN_LENGTH, 'max:'.self::PASSWORD_MAX_LENGTH],
        ];
    }

    /** Map the validated payload and the negocio of the URL to the use case input. */
    public function toCommand(): RegisterClienteCommand
    {
        $negocio = $this->route('negocio');

        return new RegisterClienteCommand(
            mb_strtolower(is_string($negocio) ? $negocio : ''),
            $this->string('nombre')->toString(),
            $this->string('correo')->toString(),
            $this->string('contrasena')->toString(),
        );
    }
}
