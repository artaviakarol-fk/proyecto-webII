<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Shared\Domain\Contracts\DatabaseStatusChecker;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Infrastructure\Persistence\DatabaseConnectionStatusChecker;
use Src\Shared\Infrastructure\Tenancy\AuthenticatedNegocioContext;

final class SharedServiceProvider extends ServiceProvider
{
    /**
     * Shared ports bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        DatabaseStatusChecker::class => DatabaseConnectionStatusChecker::class,
        NegocioContext::class => AuthenticatedNegocioContext::class,
    ];
}
