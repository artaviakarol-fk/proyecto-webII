<?php

declare(strict_types=1);

namespace Src\Identity\StaffRoles\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Identity\Users\Domain\Contracts\StaffRoleRepository;
use Src\Identity\Users\Domain\Entities\StaffRole;
use Src\Identity\Users\Domain\ValueObjects\Permission;
use Src\Shared\Domain\ValueObjects\NegocioId;
use stdClass;

/** The roles of a negocio. The table has row-level security, and every query also filters by the negocio. */
final readonly class DatabaseStaffRoleRepository implements StaffRoleRepository
{
    /** Answer a new time-ordered identifier. */
    public function nextId(): string
    {
        return (string) Str::uuid7();
    }

    /** Find a role of the negocio. */
    public function find(string $id, NegocioId $negocioId): ?StaffRole
    {
        $row = DB::table('roles')->where('negocio_id', $negocioId->value)->where('id', $id)->first();

        return $row === null ? null : $this->toDomain($row);
    }

    /** Find a role of the negocio by its name, ignoring upper and lower case. */
    public function findByName(string $name, NegocioId $negocioId): ?StaffRole
    {
        $row = DB::table('roles')
            ->where('negocio_id', $negocioId->value)
            ->whereRaw('lower(nombre) = lower(?)', [$name])
            ->first();

        return $row === null ? null : $this->toDomain($row);
    }

    /**
     * The roles of the negocio ordered by name, with how many accounts of the staff have each one, in one query.
     *
     * @return list<array{StaffRole, int}>
     */
    public function allWithUsage(NegocioId $negocioId): array
    {
        $rows = DB::table('roles')
            ->leftJoin('users', 'users.rol_id', '=', 'roles.id')
            ->where('roles.negocio_id', $negocioId->value)
            ->groupBy('roles.id', 'roles.negocio_id', 'roles.nombre', 'roles.permisos')
            ->orderBy('roles.nombre')
            ->select(['roles.id', 'roles.negocio_id', 'roles.nombre', 'roles.permisos'])
            ->selectRaw('count(users.id) as uso')
            ->get();

        $roles = [];
        foreach ($rows as $row) {
            $roles[] = [$this->toDomain($row), (int) $row->uso];
        }

        return $roles;
    }

    /** How many accounts of the staff have the role. */
    public function usage(string $id, NegocioId $negocioId): int
    {
        return DB::table('users')->where('negocio_id', $negocioId->value)->where('rol_id', $id)->count();
    }

    /** Insert a new role or write the changes of one. */
    public function save(StaffRole $role): void
    {
        DB::table('roles')->upsert(
            [[
                'id' => $role->id,
                'negocio_id' => $role->negocioId->value,
                'nombre' => $role->name,
                'permisos' => json_encode(array_map(fn (Permission $permission): string => $permission->value, $role->permissions), JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['id'],
            ['nombre', 'permisos', 'updated_at'],
        );
    }

    /** Delete a role. */
    public function delete(StaffRole $role): void
    {
        DB::table('roles')->where('negocio_id', $role->negocioId->value)->where('id', $role->id)->delete();
    }

    /** Rebuild a role from its row; the database only keeps roles with at least one known permission. */
    private function toDomain(stdClass $row): StaffRole
    {
        $stored = json_decode((string) $row->permisos, true, flags: JSON_THROW_ON_ERROR);

        return StaffRole::create(
            (string) $row->id,
            new NegocioId((string) $row->negocio_id),
            (string) $row->nombre,
            array_values(array_map(
                fn (mixed $permission): Permission => Permission::from(is_string($permission) ? $permission : ''),
                is_array($stored) ? $stored : [],
            )),
        );
    }
}
