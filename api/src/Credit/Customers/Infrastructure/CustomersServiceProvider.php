<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Credit\Customers\Application\Contracts\CreditableSales;
use Src\Credit\Customers\Application\Contracts\CreditIncreaseReader;
use Src\Credit\Customers\Application\Contracts\FiadoBalanceNotifier;
use Src\Credit\Customers\Application\UseCases\GrantCreditIncrease;
use Src\Credit\Customers\Application\UseCases\RegisterCustomer;
use Src\Credit\Customers\Application\UseCases\UpdateCustomer;
use Src\Credit\Customers\Domain\Contracts\CreditIncreaseRepository;
use Src\Credit\Customers\Domain\Contracts\CustomerRepository;
use Src\Credit\Customers\Domain\Contracts\FiadoLedger;
use Src\Credit\Customers\Infrastructure\Events\LaravelFiadoBalanceNotifier;
use Src\Credit\Customers\Infrastructure\Persistence\Ledger\DatabaseFiadoLedger;
use Src\Credit\Customers\Infrastructure\Persistence\Queries\DatabaseCreditIncreaseReader;
use Src\Credit\Customers\Infrastructure\Persistence\Repositories\EloquentCreditIncreaseRepository;
use Src\Credit\Customers\Infrastructure\Persistence\Repositories\EloquentCustomerRepository;
use Src\Credit\Customers\Infrastructure\Sales\SalesCreditableSales;

final class CustomersServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        CustomerRepository::class => EloquentCustomerRepository::class,
        FiadoLedger::class => DatabaseFiadoLedger::class,
        CreditableSales::class => SalesCreditableSales::class,
        FiadoBalanceNotifier::class => LaravelFiadoBalanceNotifier::class,
        CreditIncreaseRepository::class => EloquentCreditIncreaseRepository::class,
        CreditIncreaseReader::class => DatabaseCreditIncreaseReader::class,
    ];

    /** Give the use cases the base limit of fiado from the configuration, so they never read it themselves. */
    public function register(): void
    {
        $this->app->when([RegisterCustomer::class, UpdateCustomer::class])
            ->needs('$baseLimit')
            ->giveConfig('credit.base_limit');

        $this->app->when(GrantCreditIncrease::class)
            ->needs('$maxLimit')
            ->giveConfig('credit.max_limit');
    }
}
