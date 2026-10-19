<?php

declare(strict_types=1);

namespace Src\Negocios\Profile\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Negocios\Profile\Domain\Contracts\NegocioRepository;
use Src\Negocios\Profile\Infrastructure\Persistence\Repositories\EloquentNegocioRepository;

final class ProfileServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        NegocioRepository::class => EloquentNegocioRepository::class,
    ];
}
