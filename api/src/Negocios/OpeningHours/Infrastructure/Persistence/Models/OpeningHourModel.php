<?php

declare(strict_types=1);

namespace Src\Negocios\OpeningHours\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $negocio_id
 * @property int $dia_semana
 * @property string $hora_apertura
 * @property string $hora_cierre
 */
#[Table('horarios_negocio')]
#[Fillable(['id', 'negocio_id', 'dia_semana', 'hora_apertura', 'hora_cierre'])]
final class OpeningHourModel extends Model
{
    use HasUuids;

    /**
     * Cast the day of the week to an integer; the times stay as the strings the database answers.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['dia_semana' => 'integer'];
    }
}
