<?php

declare(strict_types=1);

namespace Src\Platform\Tenants\Infrastructure\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTenantRequest extends FormRequest
{
    /**
     * Turn a negocio on or off.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /** "activo" o "inactivo". */
            'estado' => ['required', 'string', Rule::in(['activo', 'inactivo'])],
        ];
    }

    /** Whether the negocio stays on. */
    public function active(): bool
    {
        return $this->string('estado')->value() === 'activo';
    }
}
