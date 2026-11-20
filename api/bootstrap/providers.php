<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Src\Billing\Payments\Infrastructure\PaymentsServiceProvider;
use Src\Credit\Customers\Infrastructure\CustomersServiceProvider;
use Src\Identity\StaffRoles\Infrastructure\StaffRolesServiceProvider;
use Src\Identity\Users\Infrastructure\UsersServiceProvider;
use Src\Inventory\Expirations\Infrastructure\ExpirationsServiceProvider;
use Src\Inventory\Products\Infrastructure\ProductsServiceProvider;
use Src\Inventory\Receipts\Infrastructure\ReceiptsServiceProvider;
use Src\Inventory\Reservations\Infrastructure\ReservationsServiceProvider;
use Src\Negocios\OpeningHours\Infrastructure\OpeningHoursServiceProvider;
use Src\Negocios\Profile\Infrastructure\ProfileServiceProvider;
use Src\Platform\Tenants\Infrastructure\TenantsServiceProvider;
use Src\Sales\Purchases\Infrastructure\PurchasesServiceProvider;
use Src\Sales\Reports\Infrastructure\ReportsServiceProvider;
use Src\Sales\SaleDrafts\Infrastructure\SaleDraftsServiceProvider;
use Src\Sales\Sales\Infrastructure\SalesServiceProvider;
use Src\Shared\Infrastructure\SharedServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    UsersServiceProvider::class,
    StaffRolesServiceProvider::class,
    ProfileServiceProvider::class,
    OpeningHoursServiceProvider::class,
    ProductsServiceProvider::class,
    ReceiptsServiceProvider::class,
    ExpirationsServiceProvider::class,
    ReservationsServiceProvider::class,
    SalesServiceProvider::class,
    ReportsServiceProvider::class,
    PaymentsServiceProvider::class,
    CustomersServiceProvider::class,
    SaleDraftsServiceProvider::class,
    PurchasesServiceProvider::class,
    TenantsServiceProvider::class,
];
