<?php

declare(strict_types=1);

namespace Src\Identity\Staff\Application;

use Src\Identity\Users\Domain\Contracts\StaffRoleRepository;
use Src\Identity\Users\Domain\ValueObjects\CustomRole;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class StaffRoleResolver
{
    private const string UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /** Receive the roles of the negocios. */
    public function __construct(private StaffRoleRepository $roles) {}

    /**
     * Turn what the administrator chose into a role: a default one by its name, or a role of its own negocio by its
     * id. A role of another negocio is not found, the same as one that does not exist.
     *
     * @return array{Role, ?CustomRole}
     */
    public function resolve(string $role, NegocioId $negocioId): array
    {
        $default = Role::tryFrom($role);
        if ($default !== null && in_array($default, Role::defaults(), true)) {
            return [$default, null];
        }

        $custom = preg_match(self::UUID, $role) === 1 ? $this->roles->find($role, $negocioId) : null;
        if ($custom === null) {
            throw new InvalidValueException('identity.invalid_staff_role');
        }

        return [Role::Personalizado, $custom->asCustomRole()];
    }
}
