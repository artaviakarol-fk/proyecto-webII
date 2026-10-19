<?php

declare(strict_types=1);

namespace Src\Inventory\Receipts\Infrastructure\Persistence\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Src\Inventory\Receipts\Application\Contracts\ReceiptReader;
use Src\Inventory\Receipts\Application\DTOs\ReceiptDetails;
use Src\Inventory\Receipts\Domain\ValueObjects\ReceiptId;
use Src\Inventory\Shared\Domain\ValueObjects\Money;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class DatabaseReceiptReader implements ReceiptReader
{
    /** Read a receipt of the negocio with its lines, the product names and who registered it. */
    public function find(ReceiptId $id, NegocioId $negocioId): ?ReceiptDetails
    {
        $receipt = DB::table('entradas_mercaderia as e')
            ->join('users as u', 'u.id', '=', 'e.registrada_por')
            ->where('e.negocio_id', $negocioId->value)
            ->where('e.id', $id->value)
            ->first(['e.id', 'e.proveedor', 'e.fecha', 'e.created_at', 'u.id as usuario_id', 'u.nombre as usuario_nombre']);

        if ($receipt === null) {
            return null;
        }

        $lines = DB::table('entrada_lineas as l')
            ->join('productos as p', 'p.id', '=', 'l.producto_id')
            ->where('l.negocio_id', $negocioId->value)
            ->where('l.entrada_id', $id->value)
            ->orderBy('l.id')
            ->get(['l.producto_id', 'p.codigo', 'p.nombre', 'p.precio_venta', 'l.cantidad', 'l.costo_unitario', 'l.costo_anterior']);

        $total = 0;
        $rows = [];

        foreach ($lines as $line) {
            $unitCost = Money::fromDecimal((string) $line->costo_unitario);
            $previousCost = $line->costo_anterior === null ? null : Money::fromDecimal((string) $line->costo_anterior);
            $subtotal = $unitCost->cents * (int) $line->cantidad;
            $total += $subtotal;
            $rows[] = [
                'producto_id' => (string) $line->producto_id,
                'codigo' => (string) $line->codigo,
                'nombre' => (string) $line->nombre,
                'cantidad' => (int) $line->cantidad,
                'costo_unitario' => (string) $line->costo_unitario,
                'subtotal' => new Money($subtotal)->toDecimal(),
                'costo_anterior' => $previousCost?->toDecimal(),
                'diferencia_costo' => $previousCost === null ? null : self::signedDecimal($unitCost->cents - $previousCost->cents),
                'cambio_costo' => $previousCost !== null && $previousCost->cents !== $unitCost->cents,
                'precio_venta' => (string) $line->precio_venta,
            ];
        }

        return new ReceiptDetails(
            (string) $receipt->id,
            (string) $receipt->proveedor,
            (string) $receipt->fecha,
            (string) $receipt->usuario_id,
            (string) $receipt->usuario_nombre,
            CarbonImmutable::parse((string) $receipt->created_at)->setTimezone((string) config('app.timezone'))->toIso8601String(),
            $rows,
            new Money($total)->toDecimal(),
        );
    }

    /** Write a difference of céntimos, which may be negative, with two decimals. */
    private static function signedDecimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '').new Money(abs($cents))->toDecimal();
    }
}
