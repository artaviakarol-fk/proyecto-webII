<?php

declare(strict_types=1);

namespace Src\Identity\StaffRoles\Application\UseCases;

use Src\Identity\Users\Domain\Contracts\StaffRoleRepository;
use Src\Identity\Users\Domain\Exceptions\StaffRoleConflictException;
use Src\Identity\Users\Domain\Exceptions\StaffRoleNotFoundException;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class DeleteStaffRole
{
    /** Receive the negocio of the request and its roles. */
    public function __construct(
        private NegocioContext $context,
        private StaffRoleRepository $roles,
    ) {}

    /** Delete a role of the negocio that nobody of the staff has; the default roles cannot be deleted. */
    public function execute(string $id): void
    {
        $negocioId = $this->context->current();
        $role = $this->roles->find($id, $negocioId) ?? throw StaffRoleNotFoundException::create();

        if ($this->roles->usage($role->id, $negocioId) > 0) {
            throw StaffRoleConflictException::inUse();
        }

        $this->roles->delete($role);
    }
}
