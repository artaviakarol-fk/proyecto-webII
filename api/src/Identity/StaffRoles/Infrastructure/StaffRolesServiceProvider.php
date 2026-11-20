<?php

declare(strict_types=1);

namespace Src\Identity\StaffRoles\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Identity\StaffRoles\Infrastructure\Persistence\DatabaseStaffRoleRepository;
use Src\Identity\Users\Domain\Contracts\StaffRoleRepository;

final class StaffRolesServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        StaffRoleRepository::class => DatabaseStaffRoleRepository::class,
    ];
}
