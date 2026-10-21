<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Infrastructure\Http\Requests;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Validation\Rule;
use Src\Sales\Sales\Application\DTOs\SaleFilter;
use Src\Sales\Sales\Domain\ValueObjects\SaleStatus;
use Src\Shared\Infrastructure\Http\Requests\ListRequest;

/** Filters and pagination of the sales: ?estado=cobrada&fecha=2026-10-15&pagina=2 */
final class ListSalesRequest extends ListRequest
{
    /**
     * Validation rules for the filters, added to those of the page.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** Estado de la venta: abierta, cobrada o anulada. */
            'estado' => ['sometimes', 'string', Rule::enum(SaleStatus::class)],
            /** Día en que se abrió la venta, en hora de Costa Rica (AAAA-MM-DD). */
            'fecha' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }

    /** The filters asked for. */
    public function filter(): SaleFilter
    {
        return new SaleFilter(
            $this->filled('estado') ? SaleStatus::from($this->string('estado')->toString()) : null,
            $this->filled('fecha')
                ? new DateTimeImmutable($this->string('fecha')->toString(), new DateTimeZone((string) config('app.timezone')))
                : null,
        );
    }
}
