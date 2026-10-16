<?php

declare(strict_types=1);

namespace Src\Inventory\Expirations\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Inventory\Expirations\Domain\Contracts\ExpiredUnitsRepository;
use Src\Inventory\Expirations\Infrastructure\Persistence\Repositories\DatabaseExpiredUnitsRepository;

final class ExpirationsServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        ExpiredUnitsRepository::class => DatabaseExpiredUnitsRepository::class,
    ];
}
