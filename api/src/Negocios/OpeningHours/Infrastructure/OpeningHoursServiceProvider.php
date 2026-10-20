<?php

declare(strict_types=1);

namespace Src\Negocios\OpeningHours\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Negocios\OpeningHours\Domain\Contracts\OpeningHoursRepository;
use Src\Negocios\OpeningHours\Infrastructure\Persistence\Repositories\EloquentOpeningHoursRepository;

final class OpeningHoursServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        OpeningHoursRepository::class => EloquentOpeningHoursRepository::class,
    ];
}
