<?php

declare(strict_types=1);

namespace Src\Inventory\Expirations\Infrastructure\Persistence\Queries;

use Illuminate\Support\Facades\DB;
use Src\Inventory\Expirations\Application\Contracts\ExpiredUnitsReader;
use Src\Inventory\Expirations\Application\DTOs\ExpiredUnitsSummary;
use Src\Inventory\Expirations\Domain\ValueObjects\ExpiredUnitsStatus;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class DatabaseExpiredUnitsReader implements ExpiredUnitsReader
{
    /** Read the units still on the shelf with the product and the person who marked them. */
    public function onTheShelf(NegocioId $negocioId): array
    {
        $rows = DB::table('unidades_vencidas as v')
            ->join('productos as p', 'p.id', '=', 'v.producto_id')
            ->join('users as u', 'u.id', '=', 'v.usuario_id')
            ->where('v.negocio_id', $negocioId->value)
            ->where('v.estado', ExpiredUnitsStatus::Active->value)
            ->orderByDesc('v.created_at')
            ->get(['v.id', 'v.producto_id', 'p.codigo', 'p.nombre', 'v.cantidad', 'v.motivo', 'u.nombre as marcada_por', 'v.created_at']);

        $summaries = [];

        foreach ($rows as $row) {
            $summaries[] = new ExpiredUnitsSummary(
                (string) $row->id,
                (string) $row->producto_id,
                (string) $row->codigo,
                (string) $row->nombre,
                (int) $row->cantidad,
                (string) $row->motivo,
                (string) $row->marcada_por,
                (string) $row->created_at,
            );
        }

        return $summaries;
    }
}
