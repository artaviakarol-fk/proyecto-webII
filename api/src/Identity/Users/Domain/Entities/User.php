<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\Entities;

use Src\Identity\Users\Domain\ValueObjects\Email;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Domain\ValueObjects\UserId;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class User
{
    public const int NAME_MAX_LENGTH = 120;

    /** Hold the state of an account, of the staff or of a cliente; every account belongs to one negocio. */
    private function __construct(
        public UserId $id,
        public NegocioId $negocioId,
        public string $name,
        public Email $email,
        public string $passwordHash,
        public Role $role,
        public bool $active,
    ) {}

    /**
     * Open the account of a person who buys in a negocio. The role is always cliente and is never taken
     * from the request, so nobody can register as part of the staff.
     */
    public static function registerCliente(
        UserId $id,
        NegocioId $negocioId,
        string $name,
        Email $email,
        string $passwordHash,
    ): self {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new InvalidValueException('identity.invalid_name');
        }

        return new self($id, $negocioId, $name, $email, $passwordHash, Role::Cliente, true);
    }

    /** Rebuild an account from its stored state. */
    public static function reconstitute(
        UserId $id,
        NegocioId $negocioId,
        string $name,
        Email $email,
        string $passwordHash,
        Role $role,
        bool $active,
    ): self {
        return new self($id, $negocioId, $name, $email, $passwordHash, $role, $active);
    }
}
