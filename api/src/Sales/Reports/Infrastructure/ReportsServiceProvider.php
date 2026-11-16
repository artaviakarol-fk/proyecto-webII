<?php

declare(strict_types=1);

namespace Src\Sales\Reports\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Sales\Reports\Application\Contracts\DailyClosingReader;
use Src\Sales\Reports\Infrastructure\Persistence\Queries\DatabaseDailyClosingReader;

final class ReportsServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        DailyClosingReader::class => DatabaseDailyClosingReader::class,
    ];
}
