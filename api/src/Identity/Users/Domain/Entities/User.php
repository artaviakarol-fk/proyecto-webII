<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\Entities;

use Src\Identity\Users\Domain\ValueObjects\Email;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Domain\ValueObjects\UserId;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class User
{
    /** Hold the state of a staff account; every account belongs to one negocio. */
    private function __construct(
        public UserId $id,
        public NegocioId $negocioId,
        public string $name,
        public Email $email,
        public string $passwordHash,
        public Role $role,
        public bool $active,
    ) {}

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
