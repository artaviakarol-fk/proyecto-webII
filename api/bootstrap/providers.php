<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Src\Identity\Users\Infrastructure\UsersServiceProvider;
use Src\Inventory\Expirations\Infrastructure\ExpirationsServiceProvider;
use Src\Inventory\Products\Infrastructure\ProductsServiceProvider;
use Src\Inventory\Receipts\Infrastructure\ReceiptsServiceProvider;
use Src\Negocios\OpeningHours\Infrastructure\OpeningHoursServiceProvider;
use Src\Negocios\Profile\Infrastructure\ProfileServiceProvider;
use Src\Sales\Sales\Infrastructure\SalesServiceProvider;
use Src\Shared\Infrastructure\SharedServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    UsersServiceProvider::class,
    ProfileServiceProvider::class,
    OpeningHoursServiceProvider::class,
    ProductsServiceProvider::class,
    ReceiptsServiceProvider::class,
    ExpirationsServiceProvider::class,
    SalesServiceProvider::class,
];
