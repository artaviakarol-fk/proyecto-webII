<?php

declare(strict_types=1);

namespace Src\Identity\StaffRoles\Application\UseCases;

use Src\Identity\StaffRoles\Application\DTOs\SaveStaffRoleCommand;
use Src\Identity\StaffRoles\Application\DTOs\StaffRoleView;
use Src\Identity\Users\Domain\Contracts\StaffRoleRepository;
use Src\Identity\Users\Domain\Entities\StaffRole;
use Src\Identity\Users\Domain\Exceptions\StaffRoleConflictException;
use Src\Identity\Users\Domain\ValueObjects\Permission;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class CreateStaffRole
{
    /** Receive the negocio of the request and its roles. */
    public function __construct(
        private NegocioContext $context,
        private StaffRoleRepository $roles,
    ) {}

    /** Create a role of the negocio from a name and some of the permissions that already exist. */
    public function execute(SaveStaffRoleCommand $command): StaffRoleView
    {
        $negocioId = $this->context->current();
        $role = StaffRole::create(
            $this->roles->nextId(),
            $negocioId,
            $command->name,
            array_map(fn (string $permission): Permission => Permission::from($permission), $command->permissions),
        );

        if ($this->roles->findByName($role->name, $negocioId) !== null) {
            throw StaffRoleConflictException::nameTaken();
        }

        $this->roles->save($role);

        return ListStaffRoles::view($role, 0);
    }
}
