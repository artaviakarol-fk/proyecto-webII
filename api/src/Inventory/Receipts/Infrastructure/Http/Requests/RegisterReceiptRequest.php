<?php

declare(strict_types=1);

namespace Src\Inventory\Receipts\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Inventory\Products\Application\DTOs\ProductData;
use Src\Inventory\Products\Domain\ValueObjects\Category;
use Src\Inventory\Products\Domain\ValueObjects\ProductCode;
use Src\Inventory\Products\Domain\ValueObjects\ProductName;
use Src\Inventory\Products\Infrastructure\Http\Requests\ProductRequest;
use Src\Inventory\Receipts\Application\DTOs\ReceiptLineData;
use Src\Inventory\Receipts\Application\DTOs\RegisterReceiptCommand;
use Src\Inventory\Receipts\Domain\ValueObjects\ReceiptLine;
use Src\Inventory\Receipts\Domain\ValueObjects\Supplier;
use Src\Shared\Domain\Contracts\NegocioContext;
use Stringable;

/**
 * A delivery of goods. Each line brings either "producto_id" (a product of the catalog) or
 * "producto" (the data of a new one), plus "cantidad" and "costo_unitario".
 */
final class RegisterReceiptRequest extends FormRequest
{
    public const int MAX_LINES = 100;

    /**
     * Validation rules for the delivery and its lines.
     *
     * @return array<string, list<Stringable|string>>
     */
    public function rules(NegocioContext $context): array
    {
        $negocioId = $context->current()->value;

        return [
            'proveedor' => ['required', 'string', 'max:'.Supplier::MAX_LENGTH],
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'lineas' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lineas.*' => ['array'],
            'lineas.*.producto_id' => [
                'bail',
                'required_without:lineas.*.producto',
                'prohibits:lineas.*.producto',
                'uuid',
                Rule::exists('productos', 'id')->where('negocio_id', $negocioId)->whereNull('deleted_at'),
            ],
            'lineas.*.producto' => ['required_without:lineas.*.producto_id', 'array'],
            'lineas.*.producto.codigo' => [
                'required_with:lineas.*.producto',
                'string',
                'max:'.ProductCode::MAX_LENGTH,
                'regex:'.ProductCode::PATTERN,
                'distinct',
                Rule::unique('productos', 'codigo')->where('negocio_id', $negocioId)->whereNull('deleted_at'),
            ],
            'lineas.*.producto.nombre' => ['required_with:lineas.*.producto', 'string', 'max:'.ProductName::MAX_LENGTH],
            'lineas.*.producto.categoria' => ['nullable', 'string', 'max:'.Category::MAX_LENGTH],
            'lineas.*.producto.precio_venta' => ['required_with:lineas.*.producto', 'numeric', 'decimal:0,2', 'gt:0', 'max:'.ProductRequest::MAX_AMOUNT],
            'lineas.*.producto.es_perecedero' => ['sometimes', 'boolean'],
            'lineas.*.producto.es_licor' => ['sometimes', 'boolean'],
            'lineas.*.cantidad' => ['required', 'integer', 'min:1', 'max:'.ReceiptLine::MAX_QUANTITY],
            'lineas.*.costo_unitario' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.ProductRequest::MAX_AMOUNT],
        ];
    }

    /** Map the validated payload to the use case input; who registers it comes from the token. */
    public function toCommand(): RegisterReceiptCommand
    {
        /** @var list<array<string, mixed>> $lines */
        $lines = array_values((array) $this->input('lineas', []));

        return new RegisterReceiptCommand(
            $this->string('proveedor')->toString(),
            $this->string('fecha')->toString(),
            array_map($this->line(...), $lines),
            now()->toDateTimeImmutable(),
        );
    }

    /**
     * Map one validated line.
     *
     * @param  array<string, mixed>  $line
     */
    private function line(array $line): ReceiptLineData
    {
        /** @var array<string, mixed>|null $product */
        $product = $line['producto'] ?? null;

        return new ReceiptLineData(
            isset($line['producto_id']) ? (string) $line['producto_id'] : null,
            $product === null ? null : new ProductData(
                (string) $product['codigo'],
                (string) $product['nombre'],
                isset($product['categoria']) && $product['categoria'] !== '' ? (string) $product['categoria'] : null,
                (string) $product['precio_venta'],
                '0',
                0,
                filter_var($product['es_perecedero'] ?? false, FILTER_VALIDATE_BOOLEAN),
                filter_var($product['es_licor'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ),
            (int) $line['cantidad'],
            (string) $line['costo_unitario'],
        );
    }
}
