<?php

declare(strict_types=1);

namespace Src\Identity\Staff\Application\UseCases;

use Src\Identity\Staff\Application\DTOs\ChangeStaffAccessCommand;
use Src\Identity\Staff\Application\StaffRoleResolver;
use Src\Identity\Staff\Domain\Exceptions\StaffConflictException;
use Src\Identity\Staff\Domain\Exceptions\StaffNotFoundException;
use Src\Identity\Users\Domain\Contracts\TokenRevoker;
use Src\Identity\Users\Domain\Contracts\UserRepository;
use Src\Identity\Users\Domain\Entities\User;
use Src\Identity\Users\Domain\ValueObjects\UserId;
use Src\Shared\Domain\Contracts\CurrentUser;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class ChangeStaffAccess
{
    /** Receive the negocio and the account of the request, the accounts, the roles and the revocation of tokens. */
    public function __construct(
        private NegocioContext $context,
        private CurrentUser $currentUser,
        private UserRepository $users,
        private StaffRoleResolver $roles,
        private TokenRevoker $tokens,
    ) {}

    /**
     * Give another role to an account of the staff, or turn it off or on again. The tokens of the account carry what
     * it could do, so they are revoked and the person logs in again. An administrator cannot change its own access,
     * and the negocio can never be left without an active administrator.
     */
    public function execute(string $userId, ChangeStaffAccessCommand $command): User
    {
        $negocioId = $this->context->current();
        $user = $this->users->findStaff(new UserId($userId), $negocioId) ?? throw StaffNotFoundException::create();

        $changed = $user;
        if ($command->role !== null) {
            [$role, $customRole] = $this->roles->resolve($command->role, $negocioId);
            $changed = $changed->withRole($role, $customRole);
        }
        if ($command->active !== null) {
            $changed = $changed->withActive($command->active);
        }

        $unchanged = $changed->role === $user->role
            && $changed->customRole?->id === $user->customRole?->id
            && $changed->active === $user->active;
        if ($unchanged) {
            return $user;
        }

        if ($user->id->value === $this->currentUser->id()) {
            throw StaffConflictException::ownAccount();
        }

        if ($this->stopsAdministrating($user, $changed) && $this->users->activeAdministrators($negocioId) <= 1) {
            throw StaffConflictException::lastAdministrator();
        }

        $this->users->saveAccess($changed);
        $this->tokens->revokeAll($changed->id);

        return $changed;
    }

    /** Whether an active administrator stops being one, because of its new role or because it is turned off. */
    private function stopsAdministrating(User $before, User $after): bool
    {
        return $before->active && $before->administrates() && ! ($after->active && $after->administrates());
    }
}
