<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Credit\Customers\Application\UseCases\RegisterCustomer;
use Src\Credit\Customers\Application\UseCases\UpdateCustomer;
use Src\Credit\Customers\Domain\Contracts\CustomerRepository;
use Src\Credit\Customers\Infrastructure\Persistence\Repositories\EloquentCustomerRepository;

final class CustomersServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        CustomerRepository::class => EloquentCustomerRepository::class,
    ];

    /** Give the use cases the base limit of fiado from the configuration, so they never read it themselves. */
    public function register(): void
    {
        $this->app->when([RegisterCustomer::class, UpdateCustomer::class])
            ->needs('$baseLimit')
            ->giveConfig('credit.base_limit');
    }
}
