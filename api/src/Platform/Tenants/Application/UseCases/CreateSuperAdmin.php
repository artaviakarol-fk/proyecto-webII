<?php

declare(strict_types=1);

namespace Src\Platform\Tenants\Application\UseCases;

use Src\Identity\Users\Domain\Contracts\PasswordHasher;
use Src\Identity\Users\Domain\Contracts\UserRepository;
use Src\Identity\Users\Domain\Entities\User;
use Src\Identity\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Identity\Users\Domain\ValueObjects\Email;

final readonly class CreateSuperAdmin
{
    /** Receive the accounts and the password hashing. */
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwords,
    ) {}

    /**
     * Open an account that runs the platform. There is no route for this: only somebody with access to the server
     * can do it, with the artisan command, so nobody can make themselves a superadmin from the internet.
     */
    public function execute(string $name, string $email, string $password): User
    {
        $email = new Email($email);
        if ($this->users->findByEmail($email) !== null) {
            throw EmailAlreadyRegisteredException::create();
        }

        $user = User::openSuperAdmin($this->users->nextId(), $name, $email, $this->passwords->hash($password));
        $this->users->save($user);

        return $user;
    }
}
