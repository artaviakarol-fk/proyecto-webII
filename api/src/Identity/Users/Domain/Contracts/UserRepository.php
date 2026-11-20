<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\Contracts;

use Src\Identity\Users\Domain\Entities\User;
use Src\Identity\Users\Domain\ValueObjects\Email;
use Src\Identity\Users\Domain\ValueObjects\UserId;
use Src\Shared\Domain\ValueObjects\NegocioId;

interface UserRepository
{
    /** Find the account registered with an email address. */
    public function findByEmail(Email $email): ?User;

    /** Find an account by its identifier. */
    public function find(UserId $id): ?User;

    /** Find an account of the staff of a negocio; clientes and accounts of other negocios are not found. */
    public function findStaff(UserId $id, NegocioId $negocioId): ?User;

    /**
     * The staff of a negocio ordered by name.
     *
     * @return list<User>
     */
    public function staffOf(NegocioId $negocioId): array;

    /** How many active accounts can administrate the negocio. */
    public function activeAdministrators(NegocioId $negocioId): int;

    /** Answer the identifier of the next account. */
    public function nextId(): UserId;

    /** Store a new account. */
    public function save(User $user): void;

    /** Store the new password of an existing account. */
    public function savePassword(User $user): void;

    /** Store the role and whether an existing account can log in. */
    public function saveAccess(User $user): void;
}
