<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Sales\Sales\Application\Contracts\ProductCatalog;
use Src\Sales\Sales\Application\Contracts\SaleReader;
use Src\Sales\Sales\Domain\Contracts\SaleRepository;
use Src\Sales\Sales\Infrastructure\Persistence\Queries\DatabaseProductCatalog;
use Src\Sales\Sales\Infrastructure\Persistence\Queries\DatabaseSaleReader;
use Src\Sales\Sales\Infrastructure\Persistence\Repositories\DatabaseSaleRepository;

final class SalesServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        SaleRepository::class => DatabaseSaleRepository::class,
        SaleReader::class => DatabaseSaleReader::class,
        ProductCatalog::class => DatabaseProductCatalog::class,
    ];
}
