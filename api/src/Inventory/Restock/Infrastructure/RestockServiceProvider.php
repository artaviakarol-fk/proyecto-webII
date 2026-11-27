<?php

declare(strict_types=1);

namespace Src\Inventory\Restock\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Inventory\Restock\Application\Contracts\ProductSalesReader;
use Src\Inventory\Restock\Infrastructure\Persistence\Queries\DatabaseProductSalesReader;

final class RestockServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        ProductSalesReader::class => DatabaseProductSalesReader::class,
    ];
}
