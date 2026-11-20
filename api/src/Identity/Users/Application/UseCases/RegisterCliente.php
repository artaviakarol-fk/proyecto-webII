<?php

declare(strict_types=1);

namespace Src\Identity\Users\Application\UseCases;

use Src\Identity\Users\Application\DTOs\RegisterClienteCommand;
use Src\Identity\Users\Domain\Contracts\NegocioServiceAccess;
use Src\Identity\Users\Domain\Contracts\PasswordHasher;
use Src\Identity\Users\Domain\Contracts\UserRepository;
use Src\Identity\Users\Domain\Entities\User;
use Src\Identity\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Identity\Users\Domain\Exceptions\ServiceSuspendedException;
use Src\Identity\Users\Domain\ValueObjects\Email;
use Src\Negocios\Profile\Domain\Contracts\NegocioRepository;
use Src\Negocios\Profile\Domain\Exceptions\NegocioNotFoundException;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class RegisterCliente
{
    /** Receive the user repository, the password hashing and the negocios. */
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwords,
        private NegocioRepository $negocios,
        private NegocioServiceAccess $service,
    ) {}

    /**
     * Open a cliente account in an active negocio. A negocio that does not exist and an inactive one answer the
     * same, and an email can only have one account.
     */
    public function execute(RegisterClienteCommand $command): User
    {
        $negocioId = new NegocioId($command->negocioId);

        if ($this->negocios->find($negocioId)?->active !== true) {
            throw NegocioNotFoundException::create();
        }

        // The online store of a negocio whose service is suspended is closed.
        if (! $this->service->serves($negocioId)) {
            throw ServiceSuspendedException::create();
        }

        $email = new Email($command->email);

        if ($this->users->findByEmail($email) !== null) {
            throw EmailAlreadyRegisteredException::create();
        }

        $user = User::registerCliente(
            $this->users->nextId(),
            $negocioId,
            $command->name,
            $email,
            $this->passwords->hash($command->password),
        );

        $this->users->save($user);

        return $user;
    }
}
