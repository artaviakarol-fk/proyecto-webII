<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Sales\Sales\Application\DTOs\SaleLineData;
use Src\Sales\Sales\Domain\ValueObjects\SaleLine;
use Src\Shared\Domain\Contracts\NegocioContext;
use Stringable;

/** The lines of a sale: each one names a product of the negocio and how many units. The price is never sent. */
final class SaleRequest extends FormRequest
{
    public const int MAX_LINES = 100;

    /**
     * Validation rules for the lines.
     *
     * @return array<string, list<Stringable|string>>
     */
    public function rules(NegocioContext $context): array
    {
        return [
            'lineas' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lineas.*' => ['array'],
            'lineas.*.producto_id' => [
                'bail',
                'required',
                'uuid',
                'distinct',
                Rule::exists('productos', 'id')->where('negocio_id', $context->current()->value)->whereNull('deleted_at'),
            ],
            'lineas.*.cantidad' => ['required', 'integer', 'min:1', 'max:'.SaleLine::MAX_QUANTITY],
        ];
    }

    /**
     * Map the validated lines to the use case input.
     *
     * @return list<SaleLineData>
     */
    public function toLines(): array
    {
        /** @var list<array<string, mixed>> $lines */
        $lines = array_values((array) $this->input('lineas', []));

        return array_map(
            fn (array $line): SaleLineData => new SaleLineData((string) $line['producto_id'], (int) $line['cantidad']),
            $lines,
        );
    }
}
