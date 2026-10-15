<?php

declare(strict_types=1);

namespace Src\Negocios\Profile\Infrastructure\Persistence\Models;

use Database\Factories\NegocioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $nombre
 * @property string|null $logo_path
 * @property string $estado
 */
#[Table('negocios')]
#[Fillable(['id', 'nombre', 'logo_path', 'estado'])]
#[UseFactory(NegocioFactory::class)]
final class NegocioModel extends Model
{
    /** @use HasFactory<NegocioFactory> */
    use HasFactory, HasUuids;

    public const string ACTIVE = 'activo';
}
