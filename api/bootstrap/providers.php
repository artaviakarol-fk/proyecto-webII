<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Src\Identity\Users\Infrastructure\UsersServiceProvider;
use Src\Inventory\Products\Infrastructure\ProductsServiceProvider;
use Src\Negocios\Profile\Infrastructure\ProfileServiceProvider;
use Src\Shared\Infrastructure\SharedServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    UsersServiceProvider::class,
    ProfileServiceProvider::class,
    ProductsServiceProvider::class,
];
