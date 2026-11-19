<?php

declare(strict_types=1);

namespace Src\Identity\StaffRoles\Application\UseCases;

use Src\Identity\StaffRoles\Application\DTOs\StaffRoleView;
use Src\Identity\Users\Domain\Contracts\StaffRoleRepository;
use Src\Identity\Users\Domain\Contracts\UserRepository;
use Src\Identity\Users\Domain\Entities\StaffRole;
use Src\Identity\Users\Domain\Entities\User;
use Src\Identity\Users\Domain\ValueObjects\Permission;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class ListStaffRoles
{
    /** Receive the negocio of the request, its roles and its staff. */
    public function __construct(
        private NegocioContext $context,
        private StaffRoleRepository $roles,
        private UserRepository $users,
    ) {}

    /**
     * The default roles first, which every negocio has and nobody can change, and then the roles of the negocio.
     *
     * @return list<StaffRoleView>
     */
    public function execute(): array
    {
        $negocioId = $this->context->current();
        $staff = $this->users->staffOf($negocioId);

        $views = [];
        foreach (Role::defaults() as $role) {
            $views[] = new StaffRoleView(
                $role->value,
                $role->label(),
                array_map(fn (string $ability): Permission => Permission::from($ability), $role->abilities()),
                true,
                count(array_filter($staff, fn (User $user): bool => $user->customRole === null && $user->role === $role)),
            );
        }

        foreach ($this->roles->allWithUsage($negocioId) as [$role, $usage]) {
            $views[] = self::view($role, $usage);
        }

        return $views;
    }

    /** The view of a role of the negocio. */
    public static function view(StaffRole $role, int $usage): StaffRoleView
    {
        return new StaffRoleView($role->id, $role->name, $role->permissions, false, $usage);
    }
}
