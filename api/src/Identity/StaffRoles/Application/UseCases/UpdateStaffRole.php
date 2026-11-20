<?php

declare(strict_types=1);

namespace Src\Identity\StaffRoles\Application\UseCases;

use Src\Identity\StaffRoles\Application\DTOs\SaveStaffRoleCommand;
use Src\Identity\StaffRoles\Application\DTOs\StaffRoleView;
use Src\Identity\Users\Domain\Contracts\StaffRoleRepository;
use Src\Identity\Users\Domain\Contracts\TokenRevoker;
use Src\Identity\Users\Domain\Contracts\UserRepository;
use Src\Identity\Users\Domain\Entities\User;
use Src\Identity\Users\Domain\Exceptions\StaffRoleConflictException;
use Src\Identity\Users\Domain\Exceptions\StaffRoleNotFoundException;
use Src\Identity\Users\Domain\ValueObjects\Permission;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class UpdateStaffRole
{
    /** Receive the negocio of the request, its roles, its staff and the revocation of tokens. */
    public function __construct(
        private NegocioContext $context,
        private StaffRoleRepository $roles,
        private UserRepository $users,
        private TokenRevoker $tokens,
    ) {}

    /**
     * Rename a role of the negocio or change its permissions. The tokens of whoever has the role carry the old
     * permissions, so they are revoked and those people log in again. Taking away the permission to administrate is
     * refused when it would leave the negocio without an active administrator.
     */
    public function execute(string $id, SaveStaffRoleCommand $command): StaffRoleView
    {
        $negocioId = $this->context->current();
        $role = $this->roles->find($id, $negocioId) ?? throw StaffRoleNotFoundException::create();
        $changed = $role->change($command->name, array_map(fn (string $permission): Permission => Permission::from($permission), $command->permissions));

        $sameName = $this->roles->findByName($changed->name, $negocioId);
        if ($sameName !== null && $sameName->id !== $role->id) {
            throw StaffRoleConflictException::nameTaken();
        }

        $holders = array_values(array_filter(
            $this->users->staffOf($negocioId),
            fn (User $user): bool => $user->customRole?->id === $role->id,
        ));

        if ($role->administrates() && ! $changed->administrates()) {
            $activeHolders = count(array_filter($holders, fn (User $user): bool => $user->active));
            if ($this->users->activeAdministrators($negocioId) - $activeHolders < 1) {
                throw StaffRoleConflictException::lastAdministrator();
            }
        }

        $this->roles->save($changed);
        if ($changed->permissions !== $role->permissions) {
            foreach ($holders as $holder) {
                $this->tokens->revokeAll($holder->id);
            }
        }

        return ListStaffRoles::view($changed, count($holders));
    }
}
