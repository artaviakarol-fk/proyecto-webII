<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Persistence\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string $id
 * @property string $negocio_id
 * @property string $nombre
 * @property string $correo
 * @property string $password
 * @property string $rol
 * @property bool $activo
 */
#[Table('users')]
#[Fillable(['id', 'negocio_id', 'nombre', 'correo', 'password', 'rol', 'activo'])]
#[Hidden(['password'])]
#[UseFactory(UserFactory::class)]
final class UserModel extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids;

    /**
     * Cast the boolean column to a native type.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
