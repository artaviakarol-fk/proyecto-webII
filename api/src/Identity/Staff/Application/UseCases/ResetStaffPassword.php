<?php

declare(strict_types=1);

namespace Src\Identity\Staff\Application\UseCases;

use Src\Identity\Staff\Domain\Exceptions\StaffConflictException;
use Src\Identity\Staff\Domain\Exceptions\StaffNotFoundException;
use Src\Identity\Users\Domain\Contracts\PasswordHasher;
use Src\Identity\Users\Domain\Contracts\TokenRevoker;
use Src\Identity\Users\Domain\Contracts\UserRepository;
use Src\Identity\Users\Domain\ValueObjects\UserId;
use Src\Shared\Domain\Contracts\CurrentUser;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class ResetStaffPassword
{
    /** Receive the negocio and the account of the request, the accounts, the password hashing and the revocation. */
    public function __construct(
        private NegocioContext $context,
        private CurrentUser $currentUser,
        private UserRepository $users,
        private PasswordHasher $passwords,
        private TokenRevoker $tokens,
    ) {}

    /**
     * Give a temporary password to somebody of the staff who forgot theirs. The person must change it when it logs in,
     * and the sessions it had open are closed. An administrator changes its own password from its account instead.
     */
    public function execute(string $userId, string $temporaryPassword): void
    {
        $negocioId = $this->context->current();
        $user = $this->users->findStaff(new UserId($userId), $negocioId) ?? throw StaffNotFoundException::create();

        if ($user->id->value === $this->currentUser->id()) {
            throw StaffConflictException::ownAccount();
        }

        $this->users->savePassword($user->withTemporaryPassword($this->passwords->hash($temporaryPassword)));
        $this->tokens->revokeAll($user->id);
    }
}
