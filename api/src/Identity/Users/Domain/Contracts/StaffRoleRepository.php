<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\Contracts;

use Src\Identity\Users\Domain\Entities\StaffRole;
use Src\Shared\Domain\ValueObjects\NegocioId;

interface StaffRoleRepository
{
    /** Answer the identifier of the next role. */
    public function nextId(): string;

    /** Find a role of the negocio. */
    public function find(string $id, NegocioId $negocioId): ?StaffRole;

    /** Find a role of the negocio by its name, ignoring upper and lower case. */
    public function findByName(string $name, NegocioId $negocioId): ?StaffRole;

    /**
     * The roles of the negocio ordered by name, with how many accounts of the staff have each one.
     *
     * @return list<array{StaffRole, int}>
     */
    public function allWithUsage(NegocioId $negocioId): array;

    /** How many accounts of the staff have the role. */
    public function usage(string $id, NegocioId $negocioId): int;

    /** Store a new role or the changes of one. */
    public function save(StaffRole $role): void;

    /** Delete a role that nobody has. */
    public function delete(StaffRole $role): void;
}
