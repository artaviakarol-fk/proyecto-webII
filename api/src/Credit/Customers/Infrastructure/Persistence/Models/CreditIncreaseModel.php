<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Persistence\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $negocio_id
 * @property string $cliente_id
 * @property string $monto
 * @property string $motivo
 * @property string $usuario_id
 * @property CarbonImmutable $vigente_hasta
 * @property CarbonImmutable|null $revertida_at
 * @property string|null $revertida_por
 * @property CarbonImmutable $created_at
 */
#[Table('ampliaciones_limite')]
#[Fillable(['id', 'negocio_id', 'cliente_id', 'monto', 'motivo', 'usuario_id', 'vigente_hasta', 'revertida_at', 'revertida_por', 'created_at'])]
final class CreditIncreaseModel extends Model
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
            'vigente_hasta' => 'immutable_datetime',
            'revertida_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
