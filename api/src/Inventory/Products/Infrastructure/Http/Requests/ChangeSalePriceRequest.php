<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ChangeSalePriceRequest extends FormRequest
{
    /**
     * Validation rules for the new sale price.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'precio_venta' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:'.ProductRequest::MAX_AMOUNT],
        ];
    }

    /** The new sale price as a decimal string. */
    public function salePrice(): string
    {
        return (string) $this->input('precio_venta');
    }
}
