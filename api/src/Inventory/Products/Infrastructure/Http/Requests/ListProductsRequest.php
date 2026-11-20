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
            /** Solo los productos de esta categoría, escrita como la devuelve la lista de categorías. */
            'categoria' => ['nullable', 'string', 'max:60'],
        ];
    }

    /** The category to keep, if any. */
    public function category(): ?string
    {
        return $this->filled('categoria') ? $this->string('categoria')->toString() : null;
    }

    /** Text to look for in the name or the code. */
    public function term(): ?string
    {
        return $this->filled('buscar') ? $this->string('buscar')->toString() : null;
    }
}
