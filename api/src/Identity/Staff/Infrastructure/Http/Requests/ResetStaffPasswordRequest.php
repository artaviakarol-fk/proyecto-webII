<?php

declare(strict_types=1);

namespace Src\Identity\Staff\Infrastructure\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Src\Identity\Users\Infrastructure\Http\Requests\RegisterClienteRequest;

final class ResetStaffPasswordRequest extends FormRequest
{
    /**
     * The temporary password the administrator gives.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contrasena_temporal' => ['required', 'string', 'min:'.RegisterClienteRequest::PASSWORD_MIN_LENGTH, 'max:'.RegisterClienteRequest::PASSWORD_MAX_LENGTH],
        ];
    }

    /** The temporary password. */
    public function temporaryPassword(): string
    {
        return $this->string('contrasena_temporal')->value();
    }
}
