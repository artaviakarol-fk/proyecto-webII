<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Infrastructure\Persistence\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $negocio_id
 * @property string $codigo
 * @property string $nombre
 * @property string|null $categoria
 * @property string $precio_venta
 * @property string $costo_actual
 * @property int $stock
 * @property bool $es_perecedero
 * @property bool $es_licor
 * @property string|null $foto_path
 */
#[Table('productos')]
#[Fillable([
    'id', 'negocio_id', 'codigo', 'nombre', 'categoria', 'precio_venta', 'costo_actual',
    'stock', 'es_perecedero', 'es_licor', 'foto_path',
])]
#[UseFactory(ProductFactory::class)]
final class ProductModel extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * Cast the columns to native types; amounts stay as exact decimal strings.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio_venta' => 'decimal:2',
            'costo_actual' => 'decimal:2',
            'stock' => 'integer',
            'es_perecedero' => 'boolean',
            'es_licor' => 'boolean',
        ];
    }
}
