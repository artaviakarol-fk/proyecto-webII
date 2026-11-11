<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Persistence\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * saldo_actual is read-only for the application: it is not fillable and the role cannot write it.
 *
 * @property string $id
 * @property string $negocio_id
 * @property string $nombre
 * @property string|null $telefono
 * @property string $limite_fiado
 * @property string $saldo_actual
 * @property bool $activo
 */
#[Table('clientes')]
#[Fillable(['id', 'negocio_id', 'nombre', 'telefono', 'limite_fiado', 'activo'])]
#[UseFactory(CustomerFactory::class)]
final class CustomerModel extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, HasUuids;

    /**
     * Cast the columns to native types; amounts stay as exact decimal strings.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'limite_fiado' => 'decimal:2',
            'saldo_actual' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }
}
