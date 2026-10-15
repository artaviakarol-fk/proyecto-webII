<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Search and pagination of the catalog: ?buscar=arroz&pagina=2&por_pagina=20 */
final class ListProductsRequest extends FormRequest
{
    public const int DEFAULT_PER_PAGE = 15;

    public const int MAX_PER_PAGE = 100;

    /**
     * Validation rules for the query string.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:120'],
            'pagina' => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ];
    }

    /** Text to look for in the name or the code. */
    public function term(): ?string
    {
        return $this->filled('buscar') ? $this->string('buscar')->toString() : null;
    }

    /** Requested page, starting at 1. */
    public function page(): int
    {
        return $this->integer('pagina', 1);
    }

    /** Products per page. */
    public function perPage(): int
    {
        return $this->integer('por_pagina', self::DEFAULT_PER_PAGE);
    }
}
