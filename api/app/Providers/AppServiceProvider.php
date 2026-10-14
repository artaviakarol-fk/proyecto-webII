<?php

namespace App\Providers;

use App\Application\Health\DatabaseStatusChecker;
use App\Infrastructure\Health\EloquentDatabaseStatusChecker;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DatabaseStatusChecker::class, EloquentDatabaseStatusChecker::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
