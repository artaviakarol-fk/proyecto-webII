<?php

declare(strict_types=1);

namespace Src\Platform\Tenants\Application\UseCases;

use DateTimeImmutable;
use Src\Identity\Users\Domain\Contracts\PasswordHasher;
use Src\Identity\Users\Domain\Contracts\UserRepository;
use Src\Identity\Users\Domain\Entities\User;
use Src\Identity\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Identity\Users\Domain\ValueObjects\Email;
use Src\Negocios\Profile\Domain\Contracts\NegocioRepository;
use Src\Negocios\Profile\Domain\Entities\Negocio;
use Src\Platform\Subscriptions\Domain\Contracts\SubscriptionRepository;
use Src\Platform\Subscriptions\Domain\Entities\Subscription;
use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionTerms;
use Src\Platform\Tenants\Application\Contracts\TenantDirectory;
use Src\Platform\Tenants\Application\DTOs\RegisterNegocioCommand;
use Src\Platform\Tenants\Application\DTOs\TenantSummary;
use Src\Platform\Tenants\Domain\Exceptions\TenantNotFoundException;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class RegisterNegocio
{
    /** Receive the negocios, the accounts, the password hashing, the directory of the platform and the transaction. */
    public function __construct(
        private NegocioRepository $negocios,
        private UserRepository $users,
        private PasswordHasher $passwords,
        private TenantDirectory $directory,
        private TransactionRunner $transaction,
        private SubscriptionRepository $subscriptions,
        private SubscriptionTerms $terms,
    ) {}

    /**
     * Open a negocio on the platform together with its first administrator. Both are created in one transaction:
     * there is never a negocio without somebody who can manage it. The administrator must change the temporary
     * password the first time it logs in, and from then on it opens the accounts of its own staff. The negocio starts
     * with the free trial of the platform.
     */
    public function execute(RegisterNegocioCommand $command, DateTimeImmutable $now): TenantSummary
    {
        $email = new Email($command->administratorEmail);
        $passwordHash = $this->passwords->hash($command->temporaryPassword);

        $negocioId = $this->transaction->run(function () use ($command, $email, $passwordHash, $now) {
            if ($this->users->findByEmail($email) !== null) {
                throw EmailAlreadyRegisteredException::create();
            }

            $negocio = Negocio::open($this->negocios->nextId(), $command->negocioName);
            $this->negocios->add($negocio);
            $this->users->save(User::openNegocioAdministrator(
                $this->users->nextId(),
                $negocio->id,
                $command->administratorName,
                $email,
                $passwordHash,
            ));
            $this->subscriptions->save(Subscription::startTrial($negocio->id, $now, $this->terms));

            return $negocio->id;
        });

        $tenant = $this->directory->find($negocioId) ?? throw TenantNotFoundException::create();

        return $tenant->withService($now, $this->terms);
    }
}
