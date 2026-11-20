<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\Entities;

use Src\Identity\Users\Domain\Exceptions\InvalidStaffRoleException;
use Src\Identity\Users\Domain\ValueObjects\CustomRole;
use Src\Identity\Users\Domain\ValueObjects\Permission;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Shared\Domain\ValueObjects\NegocioId;

/**
 * A role that a negocio created for its staff: a name and some of the permissions that already exist. It cannot be
 * named like a default role, and it needs at least one permission, so it always lets its staff do something.
 */
final readonly class StaffRole
{
    public const int NAME_MAX_LENGTH = 60;

    /**
     * @param  non-empty-list<Permission>  $permissions
     */
    private function __construct(
        public string $id,
        public NegocioId $negocioId,
        public string $name,
        public array $permissions,
    ) {}

    /**
     * Create a role of the negocio; repeated permissions count once and keep the order of the permissions.
     *
     * @param  list<Permission>  $permissions
     */
    public static function create(string $id, NegocioId $negocioId, string $name, array $permissions): self
    {
        return new self($id, $negocioId, self::validName($name), self::validPermissions($permissions));
    }

    /**
     * The same role with another name and other permissions.
     *
     * @param  list<Permission>  $permissions
     */
    public function change(string $name, array $permissions): self
    {
        return new self($this->id, $this->negocioId, self::validName($name), self::validPermissions($permissions));
    }

    /** Whether the role lets its staff administrate the negocio. */
    public function administrates(): bool
    {
        return in_array(Permission::Administrar, $this->permissions, true);
    }

    /** The role as an account carries it. */
    public function asCustomRole(): CustomRole
    {
        return new CustomRole($this->id, $this->name, $this->permissions);
    }

    /** A name is required, has a maximum length and cannot be the name of a role that already exists everywhere. */
    private static function validName(string $name): string
    {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw InvalidStaffRoleException::name();
        }

        foreach (Role::cases() as $role) {
            if (mb_strtolower($name) === mb_strtolower($role->label())) {
                throw InvalidStaffRoleException::reservedName();
            }
        }

        return $name;
    }

    /**
     * At least one permission; a repeated one counts once.
     *
     * @param  list<Permission>  $permissions
     * @return non-empty-list<Permission>
     */
    private static function validPermissions(array $permissions): array
    {
        $unique = [];
        foreach (Permission::cases() as $permission) {
            if (in_array($permission, $permissions, true)) {
                $unique[] = $permission;
            }
        }

        if ($unique === []) {
            throw InvalidStaffRoleException::noPermissions();
        }

        return $unique;
    }
}
