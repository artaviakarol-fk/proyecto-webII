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
 * @property string $proveedor
 * @property string $evento_id
 * @property string|null $tipo
 * @property array<string, mixed>|null $payload
 * @property Carbon $procesado_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Table('webhook_eventos')]
#[Fillable(['id', 'negocio_id', 'proveedor', 'evento_id', 'tipo', 'payload', 'procesado_at'])]
final class WebhookEventModel extends Model
{
    use HasUuids;

    /**
     * Cast the columns to native types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'procesado_at' => 'datetime',
        ];
    }
}
