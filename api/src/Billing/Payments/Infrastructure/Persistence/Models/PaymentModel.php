<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $negocio_id
 * @property string|null $venta_id
 * @property string|null $abono_id
 * @property string $proveedor
 * @property string $metodo_pago
 * @property string $moneda
 * @property string $monto
 * @property string $estado
 * @property string|null $referencia_externa
 * @property string|null $codigo_rechazo
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $resuelto_at
 * @property string|null $referencia_reembolso
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Table('pagos')]
#[Fillable([
    'id', 'negocio_id', 'venta_id', 'abono_id', 'proveedor', 'metodo_pago', 'moneda', 'monto', 'estado',
    'referencia_externa', 'codigo_rechazo', 'payload', 'resuelto_at', 'referencia_reembolso', 'created_at',
])]
final class PaymentModel extends Model
{
    use HasUuids;

    /**
     * Cast the columns to native types; the amount stays as an exact decimal string.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'payload' => 'array',
            'resuelto_at' => 'datetime',
        ];
    }
}
