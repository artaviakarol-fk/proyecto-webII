<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Infrastructure\Http\Requests;

use Src\Shared\Infrastructure\Http\Requests\ListRequest;

/** Search and pagination of the catalog: ?buscar=arroz&pagina=2&por_pagina=20 */
final class ListProductsRequest extends ListRequest
{
    /**
     * Validation rules for the search, added to those of the page.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** Texto que se busca en el nombre o el código. */
            'buscar' => ['nullable', 'string', 'max:120'],
        ];
    }

    /** Text to look for in the name or the code. */
    public function term(): ?string
    {
        return $this->filled('buscar') ? $this->string('buscar')->toString() : null;
    }
}
