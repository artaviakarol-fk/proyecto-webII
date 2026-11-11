<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Http\Requests;

use Src\Credit\Customers\Application\DTOs\CreditIncreaseFilter;
use Src\Shared\Infrastructure\Http\Requests\ListRequest;

/** Filters and pagination of the increases: ?cliente_id=...&vigentes=1&pagina=2 */
final class ListCreditIncreasesRequest extends ListRequest
{
    /**
     * Validation rules for the filters, added to those of the page.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** Solo las ampliaciones de un cliente. */
            'cliente_id' => ['sometimes', 'uuid'],
            /** Solo las que siguen vigentes (1). Sin el filtro, todas. */
            'vigentes' => ['sometimes', 'boolean'],
        ];
    }

    /** The filter of the listing at the moment of the request. */
    public function filter(): CreditIncreaseFilter
    {
        return new CreditIncreaseFilter(
            $this->filled('cliente_id') ? $this->string('cliente_id')->lower()->toString() : null,
            $this->boolean('vigentes'),
            now()->toDateTimeImmutable(),
        );
    }
}
