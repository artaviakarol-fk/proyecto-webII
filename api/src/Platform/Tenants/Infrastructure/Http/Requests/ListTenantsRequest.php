<?php

declare(strict_types=1);

namespace Src\Platform\Tenants\Infrastructure\Http\Requests;

use Src\Shared\Infrastructure\Http\Requests\ListRequest;

final class ListTenantsRequest extends ListRequest
{
    /**
     * The page and an optional part of the name of the negocio.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** Parte del nombre del negocio. */
            'buscar' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }

    /** The part of the name to look for, if any. */
    public function term(): ?string
    {
        $term = trim($this->string('buscar')->value());

        return $term === '' ? null : $term;
    }
}
