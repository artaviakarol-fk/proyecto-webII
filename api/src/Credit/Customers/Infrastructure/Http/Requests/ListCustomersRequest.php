<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Http\Requests;

use Src\Shared\Infrastructure\Http\Requests\ListRequest;

/** Search, filter and pagination of the customers: ?buscar=maría&activo=1&pagina=2 */
final class ListCustomersRequest extends ListRequest
{
    /**
     * Validation rules for the search and the filter, added to those of the page.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** Texto que se busca en el nombre o el teléfono. */
            'buscar' => ['nullable', 'string', 'max:120'],
            /** Solo los clientes activos (1) o inactivos (0). Sin el filtro, todos. */
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /** Text to look for in the name or the phone. */
    public function term(): ?string
    {
        return $this->filled('buscar') ? $this->string('buscar')->toString() : null;
    }

    /** Only the active (true) or inactive (false) customers, or all of them (null). */
    public function active(): ?bool
    {
        return $this->has('activo') ? $this->boolean('activo') : null;
    }
}
