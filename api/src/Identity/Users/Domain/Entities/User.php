<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\Entities;

use Src\Identity\Users\Domain\ValueObjects\CustomRole;
use Src\Identity\Users\Domain\ValueObjects\Email;
use Src\Identity\Users\Domain\ValueObjects\Permission;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Domain\ValueObjects\UserId;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class User
{
    public const int NAME_MAX_LENGTH = 120;

    /**
     * Hold the state of an account. Every account belongs to one negocio, except the superadmins, who run the platform
     * and belong to none. An account opened by somebody else must change its temporary password before anything else.
     */
    private function __construct(
        public UserId $id,
        public ?NegocioId $negocioId,
        public string $name,
        public Email $email,
        public string $passwordHash,
        public Role $role,
        public bool $active,
        public bool $mustChangePassword,
        public ?CustomRole $customRole = null,
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
        return new self($id, $negocioId, self::validName($name), $email, $passwordHash, Role::Cliente, true, false);
    }

    /**
     * Open the first administrator of a new negocio. The platform chooses a temporary password and gives it to the
     * owner of the negocio, so the account must change it the first time it logs in.
     */
    public static function openNegocioAdministrator(
        UserId $id,
        NegocioId $negocioId,
        string $name,
        Email $email,
        string $temporaryPasswordHash,
    ): self {
        return new self($id, $negocioId, self::validName($name), $email, $temporaryPasswordHash, Role::Administrador, true, true);
    }

    /**
     * Open an account of the staff of a negocio with a default role or a role of the negocio. The administrator
     * chooses a temporary password and gives it to the person, who must change it the first time it logs in.
     */
    public static function openStaff(
        UserId $id,
        NegocioId $negocioId,
        string $name,
        Email $email,
        string $temporaryPasswordHash,
        Role $role,
        ?CustomRole $customRole,
    ): self {
        self::assertStaffRole($role, $customRole);

        return new self($id, $negocioId, self::validName($name), $email, $temporaryPasswordHash, $role, true, true, $customRole);
    }

    /** Open an account that runs the platform; it belongs to no negocio. */
    public static function openSuperAdmin(UserId $id, string $name, Email $email, string $passwordHash): self
    {
        return new self($id, null, self::validName($name), $email, $passwordHash, Role::SuperAdmin, true, false);
    }

    /** Rebuild an account from its stored state. */
    public static function reconstitute(
        UserId $id,
        ?NegocioId $negocioId,
        string $name,
        Email $email,
        string $passwordHash,
        Role $role,
        bool $active,
        bool $mustChangePassword = false,
        ?CustomRole $customRole = null,
    ): self {
        return new self($id, $negocioId, $name, $email, $passwordHash, $role, $active, $mustChangePassword, $customRole);
    }

    /**
     * What the tokens of the account may do: the abilities of its role, or the permissions of the role of the negocio.
     *
     * @return list<string>
     */
    public function abilities(): array
    {
        return $this->customRole?->abilities() ?? $this->role->abilities();
    }

    /** The name of the role a person reads: the default one or the one the negocio gave to its own role. */
    public function roleName(): string
    {
        return $this->customRole->name ?? $this->role->label();
    }

    /** Whether the account manages its negocio, with a default role or with a role of the negocio. */
    public function administrates(): bool
    {
        return in_array(Permission::Administrar->value, $this->abilities(), true);
    }

    /** The same account of the staff with another role. */
    public function withRole(Role $role, ?CustomRole $customRole): self
    {
        self::assertStaffRole($role, $customRole);

        return new self($this->id, $this->negocioId, $this->name, $this->email, $this->passwordHash, $role, $this->active, $this->mustChangePassword, $customRole);
    }

    /** The same account, able or not to log in. */
    public function withActive(bool $active): self
    {
        return new self($this->id, $this->negocioId, $this->name, $this->email, $this->passwordHash, $this->role, $active, $this->mustChangePassword, $this->customRole);
    }

    /** The same account with a temporary password that somebody else chose: it must change it when it logs in. */
    public function withTemporaryPassword(string $passwordHash): self
    {
        return new self($this->id, $this->negocioId, $this->name, $this->email, $passwordHash, $this->role, $this->active, true, $this->customRole);
    }

    /** The same account with the password the person chose; a temporary password is no longer pending. */
    public function withPassword(string $passwordHash): self
    {
        return new self($this->id, $this->negocioId, $this->name, $this->email, $passwordHash, $this->role, $this->active, false, $this->customRole);
    }

    /** Only the staff roles can be given by a negocio, and the role of the negocio goes with its own kind. */
    private static function assertStaffRole(Role $role, ?CustomRole $customRole): void
    {
        $valid = $customRole === null
            ? in_array($role, Role::defaults(), true)
            : $role === Role::Personalizado;

        if (! $valid) {
            throw new InvalidValueException('identity.invalid_staff_role');
        }
    }

    /** A name is required and has a maximum length. */
    private static function validName(string $name): string
    {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new InvalidValueException('identity.invalid_name');
        }

        return $name;
    }
}
