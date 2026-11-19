<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\ValueObjects;

/** A role that a negocio created for its staff, as an account carries it: its id, its name and its permissions. */
final readonly class CustomRole
{
    /**
     * @param  non-empty-list<Permission>  $permissions
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $permissions,
    ) {}

    /**
     * The abilities of the tokens of an account with this role.
     *
     * @return list<string>
     */
    public function abilities(): array
    {
        return array_map(fn (Permission $permission): string => $permission->value, $this->permissions);
    }
}
