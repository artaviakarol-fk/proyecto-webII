<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Identity\Users\Domain\Contracts\UserRepository;
use Src\Identity\Users\Domain\Entities\User;
use Src\Identity\Users\Domain\ValueObjects\CustomRole;
use Src\Identity\Users\Domain\ValueObjects\Email;
use Src\Identity\Users\Domain\ValueObjects\Permission;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Domain\ValueObjects\UserId;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Shared\Domain\ValueObjects\NegocioId;
use Src\Shared\Infrastructure\Tenancy\NegocioTransaction;
use stdClass;

/**
 * The accounts table has no row-level security, because the login looks an account up by its email before knowing its
 * negocio. Every query of the staff of a negocio filters by the negocio explicitly; the roles of the negocio are read
 * with the negocio of the account, so row-level security still decides which roles can be seen.
 */
final readonly class EloquentUserRepository implements UserRepository
{
    /** Receive the transaction that reaches the rows of one negocio. */
    public function __construct(private NegocioTransaction $negocioTransaction) {}

    /** Find the account by its normalised email. */
    public function findByEmail(Email $email): ?User
    {
        $model = UserModel::query()->where('correo', $email->value)->first();

        return $model === null ? null : $this->toDomain($model);
    }

    /** Find the account by its identifier. */
    public function find(UserId $id): ?User
    {
        $model = UserModel::query()->find($id->value);

        return $model === null ? null : $this->toDomain($model);
    }

    /** Find an account of the staff of the negocio; the accounts of clientes and of other negocios are not found. */
    public function findStaff(UserId $id, NegocioId $negocioId): ?User
    {
        $model = UserModel::query()
            ->where('negocio_id', $negocioId->value)
            ->whereIn('rol', $this->staffRoles())
            ->find($id->value);

        return $model === null ? null : $this->toDomain($model);
    }

    /**
     * The staff of the negocio ordered by name, with the roles of the negocio of all of them read in one query.
     *
     * @return list<User>
     */
    public function staffOf(NegocioId $negocioId): array
    {
        $models = UserModel::query()
            ->where('negocio_id', $negocioId->value)
            ->whereIn('rol', $this->staffRoles())
            ->orderBy('nombre')
            ->orderBy('id')
            ->get()
            ->all();

        $roleIds = array_values(array_unique(array_filter(array_map(fn (UserModel $model): ?string => $model->rol_id, $models))));
        $roles = $this->customRoles($negocioId->value, $roleIds);

        return array_values(array_map(fn (UserModel $model): User => $this->toDomain($model, $roles), $models));
    }

    /** How many active accounts of the negocio can administrate it, with a default role or a role of the negocio. */
    public function activeAdministrators(NegocioId $negocioId): int
    {
        return DB::table('users')
            ->leftJoin('roles', 'roles.id', '=', 'users.rol_id')
            ->where('users.negocio_id', $negocioId->value)
            ->where('users.activo', true)
            ->where(fn ($query) => $query
                ->where('users.rol', Role::Administrador->value)
                ->orWhere(fn ($custom) => $custom
                    ->where('users.rol', Role::Personalizado->value)
                    ->whereRaw('roles.permisos @> ?::jsonb', [json_encode([Permission::Administrar->value])])))
            ->count();
    }

    /** Answer a new time-ordered identifier. */
    public function nextId(): UserId
    {
        return new UserId((string) Str::uuid7());
    }

    /** Insert the account with its password already hashed. */
    public function save(User $user): void
    {
        UserModel::query()->create([
            'id' => $user->id->value,
            'negocio_id' => $user->negocioId?->value,
            'nombre' => $user->name,
            'correo' => $user->email->value,
            'password' => $user->passwordHash,
            'rol' => $user->role->value,
            'rol_id' => $user->customRole?->id,
            'activo' => $user->active,
            'debe_cambiar_contrasena' => $user->mustChangePassword,
        ]);
    }

    /** Write the password of an existing account and whether a temporary one is still pending. */
    public function savePassword(User $user): void
    {
        UserModel::query()->whereKey($user->id->value)->update([
            'password' => $user->passwordHash,
            'debe_cambiar_contrasena' => $user->mustChangePassword,
        ]);
    }

    /** Write the role and whether an existing account can log in. */
    public function saveAccess(User $user): void
    {
        UserModel::query()->whereKey($user->id->value)->update([
            'rol' => $user->role->value,
            'rol_id' => $user->customRole?->id,
            'activo' => $user->active,
        ]);
    }

    /**
     * The roles that the staff of a negocio can have.
     *
     * @return list<string>
     */
    private function staffRoles(): array
    {
        return array_map(fn (Role $role): string => $role->value, Role::staff());
    }

    /**
     * Some roles of a negocio by their id, read with the negocio set so row-level security applies.
     *
     * @param  list<string>  $roleIds
     * @return array<string, CustomRole>
     */
    private function customRoles(string $negocioId, array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        $rows = $this->negocioTransaction->run($negocioId, fn () => DB::table('roles')->whereIn('id', $roleIds)->get(['id', 'nombre', 'permisos'])->all());

        $roles = [];
        foreach ($rows as $row) {
            $role = $this->customRole($row);
            if ($role !== null) {
                $roles[(string) $row->id] = $role;
            }
        }

        return $roles;
    }

    /** Turn a row of the roles table into the role an account carries; a role without permissions gives none. */
    private function customRole(stdClass $row): ?CustomRole
    {
        $stored = json_decode((string) $row->permisos, true, flags: JSON_THROW_ON_ERROR);
        $permissions = array_values(array_map(
            fn (mixed $permission): Permission => Permission::from(is_string($permission) ? $permission : ''),
            is_array($stored) ? $stored : [],
        ));

        return $permissions === [] ? null : new CustomRole((string) $row->id, (string) $row->nombre, $permissions);
    }

    /**
     * Rebuild the aggregate from its stored state; a role of the negocio is read when it was not read for a whole page.
     *
     * @param  array<string, CustomRole>  $roles
     */
    private function toDomain(UserModel $model, array $roles = []): User
    {
        $customRole = null;
        if ($model->rol_id !== null && $model->negocio_id !== null) {
            $customRole = $roles[$model->rol_id] ?? $this->customRoles($model->negocio_id, [$model->rol_id])[$model->rol_id] ?? null;
        }

        return User::reconstitute(
            new UserId($model->id),
            $model->negocio_id === null ? null : new NegocioId($model->negocio_id),
            $model->nombre,
            new Email($model->correo),
            $model->password,
            Role::from($model->rol),
            $model->activo,
            $model->debe_cambiar_contrasena,
            $customRole,
        );
    }
}
