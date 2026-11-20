<?php

declare(strict_types=1);

namespace Src\Platform\Tenants\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Platform\Tenants\Application\Contracts\TenantDirectory;
use Src\Platform\Tenants\Infrastructure\Console\CreateSuperAdminCommand;
use Src\Platform\Tenants\Infrastructure\Persistence\DatabaseTenantDirectory;

final class TenantsServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        TenantDirectory::class => DatabaseTenantDirectory::class,
    ];

    /** Register the command that opens a superadmin from the server. */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([CreateSuperAdminCommand::class]);
        }
    }
}
