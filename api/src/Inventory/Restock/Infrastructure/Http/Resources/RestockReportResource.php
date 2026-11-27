<?php

declare(strict_types=1);

namespace Src\Inventory\Restock\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Inventory\Restock\Application\DTOs\RestockItem;
use Src\Inventory\Restock\Application\DTOs\RestockReport;

/**
 * @property-read RestockReport $resource
 */
final class RestockReportResource extends JsonResource
{
    /** Wrap the report. */
    public function __construct(private readonly RestockReport $report)
    {
        parent::__construct($report);
    }

    /**
     * Expose the report: the products from the most urgent and the best sellers.
     *
     * @return array{dias: int, productos: list<array<string, mixed>>, mas_vendidos: list<array<string, mixed>>}
     */
    public function toArray(Request $request): array
    {
        return [
            'dias' => $this->report->windowDays,
            'productos' => array_map($this->item(...), $this->report->items),
            'mas_vendidos' => array_map($this->item(...), $this->report->topSellers),
        ];
    }

    /**
     * One product with its figures; the days left is null when it did not sell.
     *
     * @return array{id: string, codigo: string, nombre: string, categoria: string|null, stock: int, vendidas: int, promedio_diario: float, dias_restantes: float|null, estado: string, sugerido: int}
     */
    private function item(RestockItem $item): array
    {
        return [
            'id' => $item->id,
            'codigo' => $item->code,
            'nombre' => $item->name,
            'categoria' => $item->category,
            'stock' => $item->stock,
            'vendidas' => $item->sold,
            'promedio_diario' => $item->averagePerDay,
            'dias_restantes' => $item->daysLeft,
            'estado' => $item->status->value,
            'sugerido' => $item->suggested,
        ];
    }
}
