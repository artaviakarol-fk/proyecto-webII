<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Infrastructure\Persistence\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Src\Sales\Sales\Application\Contracts\SaleReader;
use Src\Sales\Sales\Application\DTOs\SaleDetails;
use Src\Sales\Sales\Domain\ValueObjects\SaleId;
use Src\Sales\Sales\Domain\ValueObjects\SaleStatus;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class DatabaseSaleReader implements SaleReader
{
    /** Read a sale of the negocio with its lines, the product names and the cashier. */
    public function find(SaleId $id, NegocioId $negocioId): ?SaleDetails
    {
        $sale = DB::table('ventas as v')
            ->join('users as u', 'u.id', '=', 'v.cajero_id')
            ->where('v.negocio_id', $negocioId->value)
            ->where('v.id', $id->value)
            ->first(['v.id', 'v.estado', 'v.metodo_pago', 'v.cedula_comprador', 'v.total', 'v.created_at', 'v.cobrada_at', 'v.anulada_at', 'u.id as cajero_id', 'u.nombre as cajero_nombre']);

        if ($sale === null) {
            return null;
        }

        $lines = DB::table('venta_lineas as l')
            ->join('productos as p', 'p.id', '=', 'l.producto_id')
            ->where('l.negocio_id', $negocioId->value)
            ->where('l.venta_id', $id->value)
            ->orderBy('l.posicion')
            ->get(['l.producto_id', 'p.codigo', 'p.nombre', 'l.cantidad', 'l.precio_unitario', 'l.subtotal']);

        $rows = [];

        foreach ($lines as $line) {
            $rows[] = [
                'producto_id' => (string) $line->producto_id,
                'codigo' => (string) $line->codigo,
                'nombre' => (string) $line->nombre,
                'cantidad' => (int) $line->cantidad,
                'precio_unitario' => (string) $line->precio_unitario,
                'subtotal' => (string) $line->subtotal,
            ];
        }

        return new SaleDetails(
            (string) $sale->id,
            (string) $sale->estado,
            (string) $sale->metodo_pago,
            (string) $sale->cajero_id,
            (string) $sale->cajero_nombre,
            $sale->cedula_comprador === null ? null : (string) $sale->cedula_comprador,
            $this->moment((string) $sale->created_at),
            $sale->cobrada_at === null ? null : $this->moment((string) $sale->cobrada_at),
            $sale->anulada_at === null ? null : $this->moment((string) $sale->anulada_at),
            $rows,
            (string) $sale->total,
            $sale->estado === SaleStatus::Open->value ? $this->expiredWarnings($negocioId, $rows) : [],
        );
    }

    /**
     * The products of the lines that have expired units on the shelf: the sale goes on, but the cashier is warned.
     *
     * @param  list<array{producto_id: string, nombre: string, ...}>  $rows
     * @return list<array{producto_id: string, nombre: string, unidades_vencidas: int}>
     */
    private function expiredWarnings(NegocioId $negocioId, array $rows): array
    {
        $expired = DB::table('unidades_vencidas')
            ->where('negocio_id', $negocioId->value)
            ->where('estado', 'activa')
            ->whereIn('producto_id', array_column($rows, 'producto_id'))
            ->groupBy('producto_id')
            ->selectRaw('producto_id, sum(cantidad) as total')
            ->pluck('total', 'producto_id');

        $warnings = [];

        foreach ($rows as $row) {
            $units = (int) ($expired[$row['producto_id']] ?? 0);

            if ($units > 0) {
                $warnings[] = ['producto_id' => $row['producto_id'], 'nombre' => $row['nombre'], 'unidades_vencidas' => $units];
            }
        }

        return $warnings;
    }

    /** Write a stored moment in the timezone of the application, as ISO 8601. */
    private function moment(string $value): string
    {
        return CarbonImmutable::parse($value)->setTimezone((string) config('app.timezone'))->toIso8601String();
    }
}
