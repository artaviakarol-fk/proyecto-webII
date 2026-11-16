<?php

declare(strict_types=1);

namespace Src\Sales\SaleDrafts\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Sales\SaleDrafts\Application\Contracts\DraftCatalog;
use Src\Sales\SaleDrafts\Application\Contracts\DraftCustomers;
use Src\Sales\SaleDrafts\Application\Contracts\SaleInterpreter;
use Src\Sales\SaleDrafts\Infrastructure\Ai\GeminiSaleInterpreter;
use Src\Sales\SaleDrafts\Infrastructure\Credit\CreditDraftCustomers;
use Src\Sales\SaleDrafts\Infrastructure\Persistence\DatabaseDraftCatalog;

final class SaleDraftsServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        DraftCatalog::class => DatabaseDraftCatalog::class,
        DraftCustomers::class => CreditDraftCustomers::class,
    ];

    /** The AI provider gets its key, model and address from config/services.php, which reads the .env file. */
    public function register(): void
    {
        $this->app->bind(SaleInterpreter::class, fn (): GeminiSaleInterpreter => new GeminiSaleInterpreter(
            is_string($key = config('services.gemini.key')) && $key !== '' ? $key : null,
            config()->string('services.gemini.model'),
            config()->string('services.gemini.base_url'),
        ));
    }
}
