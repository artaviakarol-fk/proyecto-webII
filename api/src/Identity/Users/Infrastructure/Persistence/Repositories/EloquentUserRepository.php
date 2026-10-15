<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Persistence\Repositories;

use Src\Identity\Users\Domain\Contracts\UserRepository;
use Src\Identity\Users\Domain\Entities\User;
use Src\Identity\Users\Domain\ValueObjects\Email;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Domain\ValueObjects\UserId;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class EloquentUserRepository implements UserRepository
{
    /** Find the account by its normalised email. */
    public function findByEmail(Email $email): ?User
    {
        $model = UserModel::query()->where('correo', $email->value)->first();

        return $model === null ? null : $this->toDomain($model);
    }

    /** Rebuild the aggregate from its stored state. */
    private function toDomain(UserModel $model): User
    {
        return User::reconstitute(
            new UserId($model->id),
            new NegocioId($model->negocio_id),
            $model->nombre,
            new Email($model->correo),
            $model->password,
            Role::from($model->rol),
            $model->activo,
        );
    }
}
