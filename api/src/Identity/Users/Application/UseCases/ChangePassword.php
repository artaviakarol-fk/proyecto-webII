<?php

declare(strict_types=1);

namespace Src\Identity\Users\Application\UseCases;

use Src\Identity\Users\Application\DTOs\ChangePasswordCommand;
use Src\Identity\Users\Domain\Contracts\PasswordHasher;
use Src\Identity\Users\Domain\Contracts\UserRepository;
use Src\Identity\Users\Domain\Exceptions\InvalidCredentialsException;
use Src\Identity\Users\Domain\Exceptions\WrongCurrentPasswordException;
use Src\Identity\Users\Domain\ValueObjects\UserId;
use Src\Shared\Domain\Contracts\CurrentUser;

final readonly class ChangePassword
{
    /** Receive the account of the request, the user repository and the password hashing. */
    public function __construct(
        private CurrentUser $user,
        private UserRepository $users,
        private PasswordHasher $passwords,
    ) {}

    /**
     * Change the password of the account of the request. The current password is asked even when it is a temporary
     * one, so a token left open on another device cannot take the account; after the change nothing is pending.
     */
    public function execute(ChangePasswordCommand $command): void
    {
        $user = $this->users->find(new UserId($this->user->id()));
        if ($user === null || ! $user->active) {
            throw InvalidCredentialsException::create();
        }

        if (! $this->passwords->check($command->currentPassword, $user->passwordHash)) {
            throw WrongCurrentPasswordException::create();
        }

        if ($command->newPassword === $command->currentPassword) {
            throw WrongCurrentPasswordException::same();
        }

        $this->users->savePassword($user->withPassword($this->passwords->hash($command->newPassword)));
    }
}
