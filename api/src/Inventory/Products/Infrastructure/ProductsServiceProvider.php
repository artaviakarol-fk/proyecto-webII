<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Inventory\Products\Application\Contracts\ExpiredUnitsCounter;
use Src\Inventory\Products\Application\Contracts\PriceHistoryReader;
use Src\Inventory\Products\Domain\Contracts\ProductRepository;
use Src\Inventory\Products\Infrastructure\Persistence\Repositories\DatabaseExpiredUnitsCounter;
use Src\Inventory\Products\Infrastructure\Persistence\Repositories\DatabasePriceHistoryReader;
use Src\Inventory\Products\Infrastructure\Persistence\Repositories\EloquentProductRepository;

final class ProductsServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        ProductRepository::class => EloquentProductRepository::class,
        PriceHistoryReader::class => DatabasePriceHistoryReader::class,
        ExpiredUnitsCounter::class => DatabaseExpiredUnitsCounter::class,
    ];
}
