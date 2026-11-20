<?php

declare(strict_types=1);

namespace Src\Identity\Staff\Application\UseCases;

use Src\Identity\Staff\Application\DTOs\OpenStaffAccountCommand;
use Src\Identity\Staff\Application\StaffRoleResolver;
use Src\Identity\Users\Domain\Contracts\PasswordHasher;
use Src\Identity\Users\Domain\Contracts\UserRepository;
use Src\Identity\Users\Domain\Entities\User;
use Src\Identity\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Identity\Users\Domain\ValueObjects\Email;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class OpenStaffAccount
{
    /** Receive the negocio of the request, the accounts, the roles and the password hashing. */
    public function __construct(
        private NegocioContext $context,
        private UserRepository $users,
        private StaffRoleResolver $roles,
        private PasswordHasher $passwords,
    ) {}

    /**
     * Open an account of the staff of the negocio of the administrator, with a temporary password that the person
     * changes the first time it logs in. It can be another administrator. An email can only have one account.
     */
    public function execute(OpenStaffAccountCommand $command): User
    {
        $negocioId = $this->context->current();
        [$role, $customRole] = $this->roles->resolve($command->role, $negocioId);

        $email = new Email($command->email);
        if ($this->users->findByEmail($email) !== null) {
            throw EmailAlreadyRegisteredException::create();
        }

        $user = User::openStaff(
            $this->users->nextId(),
            $negocioId,
            $command->name,
            $email,
            $this->passwords->hash($command->temporaryPassword),
            $role,
            $customRole,
        );
        $this->users->save($user);

        return $user;
    }
}
