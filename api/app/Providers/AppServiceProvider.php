<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\CarbonImmutable;
use Dedoc\Scramble\Scramble;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Src\Shared\Infrastructure\Http\Docs\ProblemResponses;

class AppServiceProvider extends ServiceProvider
{
    /** Framework defaults; module ports are bound in each module's provider. */
    public function boot(): void
    {
        Date::use(CarbonImmutable::class);
        Model::shouldBeStrict(! $this->app->isProduction());

        // The documentation of the API (/docs/api) is open to anyone who can reach the server, with or
        // without a token: it describes the endpoints and carries no data of any negocio. Scramble only
        // opens it by itself in the local environment, so the gate lets it open in the deployed one too.
        Gate::define('viewApiDocs', fn (?Authenticatable $user = null): bool => true);

        Scramble::configure()->withDocumentTransformers(ProblemResponses::class);
    }
}
