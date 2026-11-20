<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Src\Identity\Users\Application\DTOs\ChangePasswordCommand;

final class ChangePasswordRequest extends FormRequest
{
    /**
     * The current password and the new one; the new one has the same length rules as at registration.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contrasena_actual' => ['required', 'string', 'max:'.RegisterClienteRequest::PASSWORD_MAX_LENGTH],
            'contrasena_nueva' => ['required', 'string', 'min:'.RegisterClienteRequest::PASSWORD_MIN_LENGTH, 'max:'.RegisterClienteRequest::PASSWORD_MAX_LENGTH],
        ];
    }

    /** Build the command of the use case from the validated body. */
    public function toCommand(): ChangePasswordCommand
    {
        return new ChangePasswordCommand($this->string('contrasena_actual')->value(), $this->string('contrasena_nueva')->value());
    }
}
