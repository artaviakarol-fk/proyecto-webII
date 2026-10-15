<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Inventory\Products\Application\DTOs\ProductData;
use Src\Inventory\Products\Domain\ValueObjects\Category;
use Src\Inventory\Products\Domain\ValueObjects\ProductCode;
use Src\Inventory\Products\Domain\ValueObjects\ProductName;
use Src\Inventory\Products\Domain\ValueObjects\Stock;
use Src\Shared\Domain\Contracts\NegocioContext;
use Stringable;

/** Data of a product, used to create it (POST) and to replace it (PUT). */
final class ProductRequest extends FormRequest
{
    public const string MAX_AMOUNT = '99999999.99';

    /**
     * Validation rules for the data of a product of the current negocio.
     *
     * @return array<string, list<Stringable|string>>
     */
    public function rules(NegocioContext $context): array
    {
        $productId = $this->route('producto');

        return [
            'codigo' => [
                'required',
                'string',
                'max:'.ProductCode::MAX_LENGTH,
                'regex:'.ProductCode::PATTERN,
                Rule::unique('productos', 'codigo')
                    ->where('negocio_id', $context->current()->value)
                    ->whereNull('deleted_at')
                    ->ignore(is_string($productId) ? $productId : null),
            ],
            'nombre' => ['required', 'string', 'max:'.ProductName::MAX_LENGTH],
            'categoria' => ['nullable', 'string', 'max:'.Category::MAX_LENGTH],
            'precio_venta' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:'.self::MAX_AMOUNT],
            'costo_actual' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0', 'max:'.self::MAX_AMOUNT],
            'stock' => ['sometimes', 'integer', 'min:0', 'max:'.Stock::MAX],
            'es_perecedero' => ['sometimes', 'boolean'],
            'es_licor' => ['sometimes', 'boolean'],
        ];
    }

    /** Map the validated payload to the use case input; the negocio never comes from here. */
    public function toData(): ProductData
    {
        return new ProductData(
            $this->string('codigo')->toString(),
            $this->string('nombre')->toString(),
            $this->filled('categoria') ? $this->string('categoria')->toString() : null,
            (string) $this->input('precio_venta'),
            (string) $this->input('costo_actual', '0'),
            $this->integer('stock'),
            $this->boolean('es_perecedero'),
            $this->boolean('es_licor'),
        );
    }
}
